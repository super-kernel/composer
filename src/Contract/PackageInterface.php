<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Enum\AutoloadScope;
use SuperKernel\Composer\Enum\PackageType;
use SuperKernel\Contract\EnvironmentRequirementInterface;
use SuperKernel\Contract\PackageMetadataInterface;

/**
 * Exposes read-only root or lock-file package metadata.
 */
interface PackageInterface extends PackageMetadataInterface
{
    /**
     * @return PackageType|string Root for the root manifest, a known enum case, or the exact custom type
     */
    public function type(): PackageType|string;

    /**
     * @return string Lowercase Composer package name
     */
    public function name(): string;

    /**
     * @return string|null Version exactly as declared in JSON, or null when the root manifest omits it
     */
    public function version(): ?string;

    /**
     * @return string|null Distribution reference, falling back to source, or null when unavailable or for the root
     */
    public function reference(): ?string;

    /**
     * @return string Originally declared Composer type, defaulting to `library` when omitted, including for the root
     */
    public function composerType(): string;

    /**
     * Reports whether this package came from `composer.lock` `packages-dev`.
     *
     * The root package always returns false.
     */
    public function isLockedDevPackage(): bool;

    /**
     * @return string|null Canonical package directory, always the project root for Root, or null when unlocated
     */
    public function locatedPath(): ?string;

    /**
     * Returns installable package dependencies declared by this package's represented requirement scope.
     *
     * Root packages include `require` and `require-dev`; lock packages include only runtime `require` declarations.
     *
     * @return list<DependencyInterface> Dependencies sorted by normalized package name
     */
    public function dependencies(): array;

    /**
     * Reports whether dependencies() is non-empty.
     */
    public function hasDependencies(): bool;

    /**
     * Returns environment requirements declared by this package's represented requirement scope.
     *
     * Root packages include `require` and `require-dev`; lock packages include only runtime `require` declarations.
     *
     * @return list<EnvironmentRequirementInterface> Requirements sorted by normalized platform package name
     */
    public function environmentRequirements(): array;

    /**
     * Reports whether environmentRequirements() is non-empty.
     */
    public function hasEnvironmentRequirements(): bool;

    /**
     * Returns production autoload scan rules for this package.
     *
     * Root development rules are available through scanRulesFor().
     */
    public function scanRules(): ScanRuleSetInterface;

    /**
     * @return ScanRuleSetInterface Production rules, plus root autoload-dev rules in Development scope;
     *                              locked packages use production rules in either scope
     */
    public function scanRulesFor(AutoloadScope $scope): ScanRuleSetInterface;
}
