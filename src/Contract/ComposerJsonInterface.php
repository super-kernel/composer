<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

/**
 * Exposes the top-level fields defined by the official Composer package schema.
 *
 * JSON objects are recursively represented as associative arrays. Optional fields return null when absent.
 */
interface ComposerJsonInterface
{
    /**
     * Returns every declared field, including custom fields unknown to this contract.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    public function name(): ?string;

    public function description(): ?string;

    /** @return list<string>|string|null */
    public function license(): array|string|null;

    public function type(): ?string;

    public function abandoned(): bool|string|null;

    public function version(): ?string;

    public function defaultBranch(): ?bool;

    /** @return list<string>|null */
    public function nonFeatureBranches(): ?array;

    /** @return list<string>|null */
    public function keywords(): ?array;

    public function readme(): ?string;

    public function time(): ?string;

    /** @return list<array<string, mixed>>|null */
    public function authors(): ?array;

    public function homepage(): ?string;

    /** @return array<string, mixed>|null */
    public function support(): ?array;

    /** @return list<array<string, mixed>>|null */
    public function funding(): ?array;

    /** @return array<string, mixed>|null */
    public function source(): ?array;

    /** @return array<string, mixed>|null */
    public function dist(): ?array;

    /** @return list<string>|string|null */
    public function comment(): array|string|null;

    /** @return array<string, string>|null */
    public function require(): ?array;

    /** @return array<string, string>|null */
    public function requireDev(): ?array;

    /** @return array<string, string>|null */
    public function replace(): ?array;

    /** @return array<string, string>|null */
    public function conflict(): ?array;

    /** @return array<string, string>|null */
    public function provide(): ?array;

    /** @return array<string, string>|null */
    public function suggest(): ?array;

    /** @return array<array-key, mixed>|null */
    public function repositories(): ?array;

    public function minimumStability(): ?string;

    public function preferStable(): ?bool;

    /** @return array<string, mixed>|null */
    public function autoload(): ?array;

    /** @return array<string, mixed>|null */
    public function autoloadDev(): ?array;

    public function targetDir(): ?string;

    /** @return list<string>|null */
    public function includePath(): ?array;

    /** @return list<string>|string|null */
    public function bin(): array|string|null;

    /** @return array<string, mixed>|null */
    public function archive(): ?array;

    /** @return array<string, mixed>|null */
    public function phpExt(): ?array;

    /** @return array<string, mixed>|null */
    public function config(): ?array;

    /** @return array<array-key, mixed>|null */
    public function extra(): ?array;

    /** @return array<string, list<string>|string>|null */
    public function scripts(): ?array;

    /** @return array<string, string>|null */
    public function scriptsDescriptions(): ?array;

    /** @return array<string, list<string>>|null */
    public function scriptsAliases(): ?array;
}
