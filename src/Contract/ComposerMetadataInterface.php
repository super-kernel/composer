<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Constants\PackageType;

/**
 * Provides a unified view of the root package and locked packages without executing runtime code.
 */
interface ComposerMetadataInterface
{
    /**
     * Tests a case-insensitive package name, including the root when it declares a name.
     */
    public function has(string $name): bool;

    /**
     * Returns the package with the given case-insensitive name.
     *
     * Callers must first establish has($name) before calling this method. An unknown name violates that precondition
     * and causes an exception; this method never returns null. An unnamed root is accessible through root().
     *
     * @return ComposerPackageInterface The package already established by has($name)
     */
    public function get(string $name): ComposerPackageInterface;

    /**
     * @return ComposerPackageInterface The single package represented by the user's composer.json, classified as Root
     */
    public function root(): ComposerPackageInterface;

    /**
     * @return list<ComposerPackageInterface> Production and development lock packages sorted by name, excluding Root
     */
    public function all(): array;

    /**
     * Selects an exact type value; unknown custom types remain valid and distinct.
     *
     * @return list<ComposerPackageInterface> Matching packages sorted by name; Root contains only root(),
     *                                        and absent types return []
     */
    public function ofType(PackageType|string $type): array;

    /**
     * Groups every package, including Root, by its unique type value without a shared catch-all for custom types.
     *
     * @return array<array-key, non-empty-list<ComposerPackageInterface>> Type keys sorted lexically; numeric strings
     *                                                                    follow PHP array-key conversion
     */
    public function groupedByType(): array;
}
