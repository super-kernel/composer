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
use SuperKernel\Contract\VendorDirInterface;
use Throwable;

use function array_all;
use function array_is_list;
use function array_keys;
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
 * Reads, validates, and atomically publishes serialized package cache records bound to one vendor directory.
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
     * Restores a record through an explicit class allow-list and validates its identity, role, workspace, Package
     * property layout, and checksum.
     *
     * @return array{
     *     vendorDirectory: string,
     *     package: PackageInterface,
     *     classes: array<class-string, non-empty-string>,
     *     autoloadFiles: list<non-empty-string>
     * }
     *
     * @throws PackageCacheException When reading, decoding, identity, workspace, or integrity validation fails
     */
    public function read(string $relativePath, string $name, bool $root): array
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
                    'vendorDirectory',
                    'package',
                    'classes',
                    'autoloadFiles',
                ]
                || (string) $this->vendorDir !== $decoded['vendorDirectory']
                || !$decoded['package'] instanceof PackageInterface
            ) {
                throw new PackageCacheException('The package cache is invalid or incompatible.');
            }

            $package = $decoded['package'];

            if (Package::class !== $package::class) {
                throw new PackageCacheException('The package cache is invalid or incompatible.');
            }

            $properties = new ReflectionClass(Package::class)->getProperties();

            if (
                count(get_mangled_object_vars($package)) !== count($properties)
                || !array_all(
                    $properties,
                    static fn(ReflectionProperty $property): bool => $property->isInitialized($package),
                )
                || $name !== $package->name()
                || $root !== (PackageType::Root === $package->type())
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

            return $decoded;
        } catch (PackageCacheException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PackageCacheException('Unable to read the package cache.', previous: $exception);
        }
    }

    /**
     * Publishes the record through a same-directory temporary file and an atomic rename.
     *
     * @param array<class-string, non-empty-string> $classes
     * @param list<non-empty-string>                $autoloadFiles
     *
     * @throws PackageCacheException When the record cannot be encoded, its path cannot be resolved, or the file
     *                               cannot be published
     */
    public function write(string $relativePath, PackageInterface $package, array $classes, array $autoloadFiles): void
    {
        try {
            $record = [
                'vendorDirectory' => (string) $this->vendorDir,
                'package'         => $package,
                'classes'         => $classes,
                'autoloadFiles'   => $autoloadFiles,
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
