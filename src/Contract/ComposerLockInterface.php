<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

/**
 * Exposes the top-level fields defined by the official Composer lock-file schema.
 *
 * JSON objects are recursively represented as associative arrays. Optional fields return null when absent.
 */
interface ComposerLockInterface
{
    /**
     * Returns every declared field, including custom fields unknown to this contract.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /** @return list<string>|null */
    public function readme(): ?array;

    public function contentHash(): string;

    /** @return list<array<string, mixed>> */
    public function packages(): array;

    /** @return list<array<string, mixed>> */
    public function packagesDev(): array;

    /** @return list<array<string, mixed>>|null */
    public function aliases(): ?array;

    public function minimumStability(): ?string;

    /** @return array<string, int>|null */
    public function stabilityFlags(): ?array;

    public function preferStable(): ?bool;

    public function preferLowest(): ?bool;

    /** @return array<string, string>|null */
    public function platform(): ?array;

    /** @return array<string, string>|null */
    public function platformDev(): ?array;

    /** @return array<string, string>|null */
    public function platformOverrides(): ?array;

    public function pluginApiVersion(): ?string;
}
