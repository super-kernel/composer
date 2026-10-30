<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Cache;

use SuperKernel\Composer\Exception\PackageCacheException;
use SuperKernel\Contract\VendorDirInterface;

use function dirname;
use function explode;
use function file_exists;
use function is_dir;
use function is_link;
use function mkdir;
use function rtrim;

/**
 * Resolves package cache paths below the vendor `.cache/classes` directory and guards every parent directory.
 *
 * @internal
 */
final readonly class PackageCachePath
{
    public function __construct(private VendorDirInterface $vendorDir)
    {
    }

    /**
     * @param bool $create Whether missing parent directories are created
     *
     * @throws PackageCacheException When the vendor directory is empty, a parent is a link or not a directory,
     *                               or a missing parent cannot be created
     */
    public function resolve(string $relativePath, bool $create = false): string
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
}
