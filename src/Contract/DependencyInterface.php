<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

/**
 * Describes one installable Composer package required by a package.
 */
interface DependencyInterface
{
    /**
     * @return string Lowercase Composer package name
     */
    public function name(): string;

    /**
     * @return string Constraint exactly as represented by the owning Composer metadata source
     */
    public function constraint(): string;

    /**
     * Reports whether the root package declared the requirement in `require-dev`.
     *
     * Dependency-package `require-dev` declarations are root-only and are not represented.
     */
    public function isDevRequirement(): bool;
}
