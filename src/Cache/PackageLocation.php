<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Cache;

use SuperKernel\Composer\Path\RelativePath;

/**
 * Expresses a package location relative to its environment: the project root for the root package and the vendor
 * directory for dependencies.
 *
 * @internal
 */
final class PackageLocation
{
    /**
     * Returns `.` for the root package, a vendor-relative path for a dependency located below the vendor directory, the
     * unchanged absolute path for one located elsewhere, and null for a package without a location.
     */
    public static function persisted(?string $locatedPath, bool $root, string $vendorDirectory): ?string
    {
        if ($root) {
            return '.';
        }

        return null === $locatedPath ? null : RelativePath::relativize($locatedPath, $vendorDirectory);
    }
}
