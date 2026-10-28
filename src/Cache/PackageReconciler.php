<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Cache;

use ReflectionClass;
use ReflectionProperty;
use SuperKernel\Attribute\Provider;
use SuperKernel\Composer\ClassMap\ClassMapBuilder;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\PackageParserInterface;
use SuperKernel\Composer\Contract\PackageReconcilerInterface;
use SuperKernel\Composer\Enum\AutoloadScope;
use SuperKernel\Composer\Enum\PackageType;
use SuperKernel\Composer\Enum\ScanRuleKind;
use SuperKernel\Composer\Enum\ScanRuleSource;
use SuperKernel\Composer\Exception\ManifestException;
use SuperKernel\Composer\Exception\PackageCacheException;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\Composer\Metadata\ComposerPackageName;
use SuperKernel\Composer\Model\Dependency;
use SuperKernel\Composer\Model\EnvironmentRequirement;
use SuperKernel\Composer\Model\Package;
use SuperKernel\Composer\Model\ScanRule;
use SuperKernel\Composer\Model\ScanRuleSet;
use SuperKernel\Contract\VendorDirInterface;
use SuperKernel\ScanIsolation\Contract\ScanProcessorInterface;
use SuperKernel\ScanIsolation\Exception\ScanProcessorUnavailableException;
use Throwable;

use function array_all;
use function array_is_list;
use function array_keys;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function get_mangled_object_vars;
use function hash;
use function hash_equals;
use function is_array;
use function is_dir;
use function is_file;
use function is_link;
use function is_string;
use function mkdir;
use function realpath;
use function rename;
use function rtrim;
use function serialize;
use function sprintf;
use function str_contains;
use function strlen;
use function tempnam;
use function unlink;
use function unserialize;

/**
 * Reconciles one package inside the scan processor and returns its subsequently restored cache instance.
 *
 * @internal
 */
#[Provider(PackageReconcilerInterface::class)]
final class PackageReconciler implements PackageReconcilerInterface
{
    /** @var array<string, array<class-string, non-empty-string>> */
    private array $classes = [];

    /** @var array<string, list<non-empty-string>> */
    private array $autoloadFiles = [];

    public function __construct(
        private readonly PackageParserInterface $parser,
        private readonly ClassMapBuilder        $builder,
        private readonly VendorDirInterface     $vendorDir,
        private readonly ScanProcessorInterface $scanProcessor,
    )
    {
    }

    public function reconcile(ComposerPackageInterface $metadata): PackageInterface
    {
        if (!$this->scanProcessor->support()) {
            throw new ScanProcessorUnavailableException('The supplied package scan processor is unsupported.');
        }

        $name = $metadata->name() ?? throw new ManifestException('Root composer.json must declare a string name.');

        if (!ComposerPackageName::isPackage($name)) {
            throw new PackageCacheException('The package cache requires a normalized Composer package name.');
        }

        $path = $name . '.ser';
        $root = PackageType::Root === $metadata->type();
        $locatedPath = $this->parser->locate($metadata);

        $this->scanProcessor->execute(function () use ($metadata, $name, $path, $root, $locatedPath): void {
            if (!$root && is_file($this->path($path))) {
                try {
                    $cached = $this->read($path, $name, $root);

                    if ($cached['package']->reference() === $metadata->reference()) {
                        return;
                    }
                } catch (PackageCacheException) {
                    // An invalid previous package can be replaced only inside a successful scan.
                }
            }

            $package = $this->parser->parse($metadata, $locatedPath);
            $this->write(
                $path,
                $package,
                $this->builder->buildPackage($package, $package->scanRulesFor(AutoloadScope::Development)),
                $this->parser->autoloadFiles($metadata, $locatedPath),
            );
        });

        $restored = $this->read($path, $name, $root);
        $package = $restored['package'];
        $restoredPath = $package->locatedPath();

        if (
            PackageType::Metapackage !== $package->type()
            && $restoredPath !== $locatedPath
            && (null === $restoredPath || !is_dir($restoredPath))
        ) {
            throw new PackageLocationException(sprintf(
                'Package "%s" has no existing directory (cached location: %s).',
                $name,
                $restoredPath ?? 'none',
            ));
        }

        $this->classes[$name] = $restored['classes'];
        $this->autoloadFiles[$name] = $restored['autoloadFiles'];

        return $package;
    }

    public function classes(string $packageName): array
    {
        return $this->classes[$packageName] ?? [];
    }

    public function autoloadFiles(string $packageName): array
    {
        return $this->autoloadFiles[$packageName] ?? [];
    }

    /**
     * @return array{
     *     vendorDirectory: string,
     *     package: PackageInterface,
     *     classes: array<class-string, non-empty-string>,
     *     autoloadFiles: list<non-empty-string>
     * }
     *
     * @throws PackageCacheException When decoding, identity, workspace, or integrity validation fails
     */
    private function read(string $path, string $name, bool $root): array
    {
        try {
            $filename = $this->path($path);

            if (is_link($filename) || !is_file($filename) || false === ($contents = @file_get_contents($filename))) {
                throw new PackageCacheException('Unable to read the package cache.');
            }

            $decoded = @unserialize($contents, [
                'allowed_classes' => [
                    Package::class,
                    PackageType::class,
                    Dependency::class,
                    EnvironmentRequirement::class,
                    ScanRule::class,
                    ScanRuleSet::class,
                    ScanRuleKind::class,
                    ScanRuleSource::class,
                ],
            ]);

            if (!is_array($decoded) || !is_string($decoded['checksum'] ?? null)) {
                throw new PackageCacheException('The package cache is invalid or incompatible.');
            }

            $checksum = $decoded['checksum'];
            unset($decoded['checksum']);

            if (
                array_keys($decoded) !== [
                    'vendorDirectory',
                    'package',
                    'classes',
                    'autoloadFiles',
                ]
                || (string) $this->vendorDir !== $decoded['vendorDirectory']
                || !$decoded['package'] instanceof PackageInterface
                || !self::validPackageShape($decoded['package'])
                || $name !== $decoded['package']->name()
                || $root !== (PackageType::Root === $decoded['package']->type())
                || !is_array($decoded['classes'])
                || !is_array($decoded['autoloadFiles'])
                || !array_is_list($decoded['autoloadFiles'])
                || !hash_equals($checksum, hash('sha256', serialize($decoded)))
            ) {
                throw new PackageCacheException('The package cache is invalid or incompatible.');
            }

            foreach ($decoded['classes'] as $class => $file) {
                if (!is_string($class) || '' === $class || str_contains($class, "\0") || !self::validPath($file)) {
                    throw new PackageCacheException('The package cache contains an invalid class map.');
                }
            }

            foreach ($decoded['autoloadFiles'] as $file) {
                if (!self::validPath($file)) {
                    throw new PackageCacheException('The package cache contains an invalid autoload file.');
                }
            }

            return $decoded;
        } catch (PackageCacheException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PackageCacheException('Unable to read the package cache.', previous: $exception);
        }
    }

    /**
     * @param array<class-string, non-empty-string> $classes
     * @param list<non-empty-string>                $autoloadFiles
     *
     * @throws PackageCacheException When the parsed package cannot be published
     */
    private function write(string $path, PackageInterface $package, array $classes, array $autoloadFiles): void
    {
        try {
            $record = [
                'vendorDirectory' => (string) $this->vendorDir,
                'package'         => $package,
                'classes'         => $classes,
                'autoloadFiles'   => $autoloadFiles,
            ];
            $record['checksum'] = hash('sha256', serialize($record));
            $this->writeFile($path, serialize($record));
        } catch (Throwable $exception) {
            throw new PackageCacheException('Unable to write the package cache.', previous: $exception);
        }
    }

    private static function validPackageShape(PackageInterface $package): bool
    {
        if (Package::class !== $package::class) {
            return false;
        }

        $properties = new ReflectionClass(Package::class)->getProperties();

        if (count(get_mangled_object_vars($package)) !== count($properties)) {
            return false;
        }

        return array_all(
            $properties,
            static fn(ReflectionProperty $property): bool => $property->isInitialized($package),
        );
    }

    private static function validPath(mixed $path): bool
    {
        return is_string($path) && '' !== $path && !str_contains($path, "\0");
    }

    private function path(string $relativePath, bool $create = false): string
    {
        $vendor = rtrim((string) $this->vendorDir, '/');

        if ('' === $vendor) {
            throw new PackageCacheException('The package cache requires a non-empty vendor directory.');
        }

        $directory = $vendor;

        foreach (explode('/', '.cache/classes/' . dirname($relativePath)) as $segment) {
            if ('.' === $segment) {
                continue;
            }

            $directory .= '/' . $segment;

            if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
                throw new PackageCacheException('The package cache parent is not a regular directory.');
            }

            if ($create && !is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) {
                throw new PackageCacheException('Unable to create the package cache directory.');
            }
        }

        return $vendor . '/.cache/classes/' . $relativePath;
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $path = $this->path($relativePath, true);
        $temporary = @tempnam(dirname($path), '.classes-');

        if (false === $temporary) {
            throw new PackageCacheException('Unable to create a package cache temporary file.');
        }

        try {
            if (
                realpath(dirname($path)) !== realpath(dirname($temporary))
                || strlen($contents) !== @file_put_contents($temporary, $contents)
                || !@rename($temporary, $path)
            ) {
                throw new PackageCacheException('Unable to publish the package cache file.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
