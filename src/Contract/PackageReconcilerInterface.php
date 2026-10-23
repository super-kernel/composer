<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Exception\ManifestException;
use SuperKernel\Composer\Exception\PackageCacheException;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\ScanIsolation\Exception\ScanProcessorUnavailableException;

/**
 * Reconciles native package metadata with persisted runtime packages and their derived autoload data.
 */
interface PackageReconcilerInterface
{
    /**
     * Reuses a locked package with the same reference; Root is reparsed on every scan.
     *
     * Package directories are checked even when cached data is reused. The returned package and its derived data
     * come from the authoritative restoration after processing. Exposed class maps and file lists change only after
     * successful restoration.
     *
     * @return PackageInterface The instance restored after the processor completes
     *
     * @throws ManifestException                 When the root manifest does not declare a package name
     * @throws PackageCacheException             When the package cache cannot be written or restored after processing
     * @throws PackageLocationException          When a located or restored package directory is missing
     * @throws ScanProcessorUnavailableException When the supplied processor is unsupported
     */
    public function reconcile(ComposerPackageInterface $metadata): PackageInterface;

    /**
     * @return array<class-string, non-empty-string> Class map from the package's last successful reconciliation,
     *                                               or an empty map when it has not been reconciled
     */
    public function classes(string $packageName): array;

    /**
     * @return list<non-empty-string> Autoload files in declaration order from the last successful reconciliation,
     *                                or an empty list when the package has not been reconciled
     */
    public function autoloadFiles(string $packageName): array;
}
