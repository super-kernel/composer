<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Enum\PackageType;

/**
 * Provides normalized native fields for one root or lock package, independently of its installation state.
 *
 * Missing optional scalar fields return null and missing collections return []. License, bin, and comment strings
 * become lists. Field accessors preserve declared values; they do not resolve dependencies or execute scripts.
 */
interface ComposerPackageInterface
{
    /**
     * @return PackageType|string Root for the root manifest, a known enum case, or the exact custom type
     *                            string; an omitted dependency type defaults to Library
     */
    public function type(): PackageType|string;

    /**
     * @return non-empty-string|null Lowercase package name; null only when the root manifest omits its name
     */
    public function name(): ?string;

    /**
     * Reports membership in `composer.lock` packages-dev; the root always returns false.
     */
    public function isDev(): bool;

    /**
     * Returns only this package's declared ext-* requirement names, including require-dev when requested.
     *
     * This is package-local metadata, not a transitive application requirement calculation. PHP, lib-* and Composer
     * API requirements and suggested extensions are excluded. Constraints remain available through require()
     * and requireDev().
     *
     * @return list<string> Unique lowercase ext-* names sorted lexically
     */
    public function extensions(bool $includeDev = true): array;

    /**
     * Returns production autoload declarations without checking or loading their paths.
     *
     * @return ComposerAutoloadInterface Production declarations, including empty rules when no autoload field exists
     */
    public function autoload(): ComposerAutoloadInterface;

    /**
     * Returns this package's development autoload declarations without applying them to a consuming project.
     *
     * @return ComposerAutoloadInterface Package-local development declarations, including empty rules when absent
     */
    public function autoloadDev(): ComposerAutoloadInterface;

    /**
     * @return string|null Declared dist.reference, falling back to source.reference, or null;
     *                     never derived from a version
     */
    public function reference(): ?string;

    /**
     * @return string|null Version exactly as declared, or null when absent
     */
    public function version(): ?string;

    /**
     * Returns the original package record, recursively converting JSON objects to native associative arrays.
     *
     * Derived Root type, development membership, defaults, and normalized accessor lists do not overwrite this record.
     * Custom fields and the originally declared package type remain available here.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;

    public function description(): ?string;

    /** @return list<string> */
    public function license(): array;

    /** @return list<string> */
    public function keywords(): array;

    public function readme(): ?string;

    public function time(): ?string;

    /** @return list<array<string, string>> */
    public function authors(): array;

    public function homepage(): ?string;

    /** @return array<string, mixed> */
    public function support(): array;

    /** @return list<array<string, string>> */
    public function funding(): array;

    /** @return array<string, mixed>|null */
    public function source(): ?array;

    /** @return array<string, mixed>|null */
    public function dist(): ?array;

    /** @return array<string, string> */
    public function require(): array;

    /** @return array<string, string> */
    public function requireDev(): array;

    /** @return array<string, string> */
    public function replace(): array;

    /** @return array<string, string> */
    public function conflict(): array;

    /** @return array<string, string> */
    public function provide(): array;

    /** @return array<string, string> */
    public function suggest(): array;

    /** @return list<string> */
    public function bin(): array;

    /** @return array<array-key, mixed> */
    public function extra(): array;

    public function abandoned(): bool|string|null;

    public function defaultBranch(): ?bool;

    public function normalizedVersion(): ?string;

    public function notificationUrl(): ?string;

    public function installationSource(): ?string;

    /** @return array<string, mixed> */
    public function transportOptions(): array;

    /** @return list<string> */
    public function nonFeatureBranches(): array;

    /** @return list<string> */
    public function includePath(): array;

    public function targetDir(): ?string;

    /** @return array<string, mixed> */
    public function archive(): array;

    /** @return array<string, mixed> */
    public function phpExt(): array;

    /** @return list<string> */
    public function comment(): array;

    /** @return array<array-key, mixed> */
    public function repositories(): array;

    public function minimumStability(): ?string;

    public function preferStable(): ?bool;

    /** @return array<string, mixed> */
    public function config(): array;

    /** @return array<string, list<string>|string> */
    public function scripts(): array;

    /** @return array<string, string> */
    public function scriptsDescriptions(): array;

    /** @return array<string, list<string>> */
    public function scriptsAliases(): array;
}
