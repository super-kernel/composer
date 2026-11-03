<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Cache;

use ReflectionClass;
use ReflectionProperty;
use SuperKernel\Composer\Constants\PackageType;
use SuperKernel\Composer\Constants\ScanRuleKind;
use SuperKernel\Composer\Constants\ScanRuleSource;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Exception\PackageCacheException;
use SuperKernel\Composer\Model\Dependency;
use SuperKernel\Composer\Model\EnvironmentRequirement;
use SuperKernel\Composer\Model\Package;
use SuperKernel\Composer\Model\ScanRule;
use SuperKernel\Composer\Model\ScanRuleSet;
use SuperKernel\Composer\Path\RelativePath;
use SuperKernel\Contract\VendorDirInterface;
use Throwable;

use function array_all;
use function array_is_list;
use function array_keys;
use function array_map;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function get_mangled_object_vars;
use function hash;
use function hash_equals;
use function is_array;
use function is_file;
use function is_link;
use function is_string;
use function realpath;
use function rename;
use function serialize;
use function str_contains;
use function strlen;
use function tempnam;
use function unlink;
use function unserialize;

/**
 * Reads, validates, and atomically publishes relocatable package cache records.
 *
 * A record keeps the package location relative to its environment and the class map and file autoloads relative to
 * the package directory, so it stays valid when the project moves or is packaged into a Phar.
 *
 * @internal
 */
final readonly class PackageCacheStorage
{
    public function __construct(private PackageCachePath $path, private VendorDirInterface $vendorDir)
    {
    }

    /**
     * @throws PackageCacheException When the cache path cannot be resolved
     */
    public function isFile(string $relativePath): bool
    {
        return is_file($this->path->resolve($relativePath));
    }

    /**
     * Restores a record through an explicit class allow-list, validates its identity, role, location, Package property
     * layout, and checksum, and resolves its paths against the package's current location.
     *
     * @param string|null $locatedPath The package directory located in the current environment
     *
     * @return array{
     *     package: PackageInterface,
     *     classes: array<class-string, non-empty-string>,
     *     autoloadFiles: list<non-empty-string>
     * }
     *
     * @throws PackageCacheException When reading, decoding, identity, location, or integrity validation fails
     */
    public function read(string $relativePath, string $name, bool $root, ?string $locatedPath): array
    {
        try {
            $filename = $this->path->resolve($relativePath);

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
                    'package',
                    'classes',
                    'autoloadFiles',
                ]
                || !$decoded['package'] instanceof PackageInterface
            ) {
                throw new PackageCacheException('The package cache is invalid or incompatible.');
            }

            $package = $decoded['package'];

            if (Package::class !== $package::class) {
                throw new PackageCacheException('The package cache is invalid or incompatible.');
            }

            $properties = new ReflectionClass(Package::class)->getProperties();
            $persistedLocation = PackageLocation::persisted($locatedPath, $root, (string) $this->vendorDir);

            if (
                count(get_mangled_object_vars($package)) !== count($properties)
                || !array_all(
                    $properties,
                    static fn(ReflectionProperty $property): bool => $property->isInitialized($package),
                )
                || $name !== $package->name()
                || $root !== (PackageType::Root === $package->type())
                || $persistedLocation !== $package->locatedPath()
                || !is_array($decoded['classes'])
                || !is_array($decoded['autoloadFiles'])
                || !array_is_list($decoded['autoloadFiles'])
                || !hash_equals($checksum, hash('sha256', serialize($decoded)))
            ) {
                throw new PackageCacheException('The package cache is invalid or incompatible.');
            }

            foreach ($decoded['classes'] as $class => $file) {
                if (
                    !is_string($class)
                    || '' === $class
                    || str_contains($class, "\0")
                    || !is_string($file)
                    || '' === $file
                    || str_contains($file, "\0")
                ) {
                    throw new PackageCacheException('The package cache contains an invalid class map.');
                }
            }

            foreach ($decoded['autoloadFiles'] as $file) {
                if (!is_string($file) || '' === $file || str_contains($file, "\0")) {
                    throw new PackageCacheException('The package cache contains an invalid autoload file.');
                }
            }

            $resolve = static fn(string $file): string => null === $locatedPath
                ? $file
                : RelativePath::resolve($file, $locatedPath);

            return [
                'package'       => $package->withLocatedPath($locatedPath),
                'classes'       => array_map($resolve, $decoded['classes']),
                'autoloadFiles' => array_map($resolve, $decoded['autoloadFiles']),
            ];
        } catch (PackageCacheException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PackageCacheException('Unable to read the package cache.', previous: $exception);
        }
    }

    /**
     * Publishes the record through a same-directory temporary file and an atomic rename.
     *
     * The persisted package carries its environment-relative location, and paths below the package directory become
     * package-relative; other paths stay absolute.
     *
     * @param array<class-string, non-empty-string> $classes
     * @param list<non-empty-string>                $autoloadFiles
     *
     * @throws PackageCacheException When the package is not a Package model, the record cannot be encoded, its path
     *                               cannot be resolved, or the file cannot be published
     */
    public function write(string $relativePath, PackageInterface $package, array $classes, array $autoloadFiles): void
    {
        try {
            if (!$package instanceof Package) {
                throw new PackageCacheException('The package cache stores only Package models.');
            }

            $locatedPath = $package->locatedPath();
            $root = PackageType::Root === $package->type();
            $persistedLocation = PackageLocation::persisted($locatedPath, $root, (string) $this->vendorDir);
            $relativize = static fn(string $file): string => null === $locatedPath
                ? $file
                : RelativePath::relativize($file, $locatedPath);
            $record = [
                'package'       => $package->withLocatedPath($persistedLocation),
                'classes'       => array_map($relativize, $classes),
                'autoloadFiles' => array_map($relativize, $autoloadFiles),
            ];
            $record['checksum'] = hash('sha256', serialize($record));
            $contents = serialize($record);
            $path = $this->path->resolve($relativePath, true);
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
        } catch (Throwable $exception) {
            throw new PackageCacheException('Unable to write the package cache.', previous: $exception);
        }
    }
}
