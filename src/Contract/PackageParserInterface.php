<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Exception\AutoloadRuleException;
use SuperKernel\Composer\Exception\LockFileException;
use SuperKernel\Composer\Exception\ManifestException;
use SuperKernel\Composer\Exception\PackageLocationException;

/**
 * Adapts native Composer package fields to runtime packages without reading another metadata source.
 */
interface PackageParserInterface
{
    /**
     * @return non-empty-string|null Project root, canonical dependency directory, or null for a metapackage
     *
     * @throws PackageLocationException When an installable package cannot be located in an existing directory
     */
    public function locate(ComposerPackageInterface $metadata): ?string;

    /**
     * Adapts the supplied metadata using a previously located directory, without locating the package again.
     * Root includes production and development requirements and scan rules; dependencies use production fields only.
     *
     * @return PackageInterface Runtime package with interpreted requirements, location, and scan rules
     *
     * @throws ManifestException     When the runtime root has no package name or has invalid requirements
     * @throws LockFileException     When a dependency has invalid requirements, type, or autoload rules
     * @throws AutoloadRuleException When root autoload declarations are unsafe or unsupported
     */
    public function parse(ComposerPackageInterface $metadata, ?string $locatedPath): PackageInterface;

    /**
     * Includes development files only for Root and does not execute the files.
     *
     * @return list<non-empty-string> Existing file-autoload paths in declaration order
     *
     * @throws AutoloadRuleException When a file-autoload path is unsafe or unsupported
     */
    public function autoloadFiles(ComposerPackageInterface $metadata, ?string $locatedPath): array;
}
