<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Exception\PackageLocationException;

/**
 * Supplies external package-location knowledge for lock-file entries.
 */
interface PackageLocatorInterface
{
    /**
     * Locates a package without inferring whether Composer installed it.
     *
     * The runtime adapter supplies only non-root, non-metapackage records and verifies the returned directory.
     * A null or invalid candidate causes {@see PackageLocationException}, without a fallback to the standard layout.
     *
     * @param string $packageName     Lowercase Composer package name
     * @param string $composerType    Composer type exactly as represented by project metadata
     * @param string $vendorDirectory Absolute vendor directory selected for the project
     *
     * @return string|null Absolute path or vendor-relative path to a candidate directory, or null when unlocated
     */
    public function locate(string $packageName, string $composerType, string $vendorDirectory): ?string;
}
