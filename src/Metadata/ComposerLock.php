<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use SuperKernel\Composer\Contract\ComposerLockInterface;

final readonly class ComposerLock implements ComposerLockInterface
{
    /**
     * @param array<string, mixed> $fields Fields validated and normalized by ComposerFileReader
     */
    public function __construct(private array $fields)
    {
    }

    public function all(): array
    {
        return $this->fields;
    }

    public function readme(): ?array
    {
        return $this->fields['_readme'] ?? null;
    }

    public function contentHash(): string
    {
        return $this->fields['content-hash'];
    }

    public function packages(): array
    {
        return $this->fields['packages'];
    }

    public function packagesDev(): array
    {
        return $this->fields['packages-dev'];
    }

    public function aliases(): ?array
    {
        return $this->fields['aliases'] ?? null;
    }

    public function minimumStability(): ?string
    {
        return $this->fields['minimum-stability'] ?? null;
    }

    public function stabilityFlags(): ?array
    {
        return $this->fields['stability-flags'] ?? null;
    }

    public function preferStable(): ?bool
    {
        return $this->fields['prefer-stable'] ?? null;
    }

    public function preferLowest(): ?bool
    {
        return $this->fields['prefer-lowest'] ?? null;
    }

    public function platform(): ?array
    {
        return $this->fields['platform'] ?? null;
    }

    public function platformDev(): ?array
    {
        return $this->fields['platform-dev'] ?? null;
    }

    public function platformOverrides(): ?array
    {
        return $this->fields['platform-overrides'] ?? null;
    }

    public function pluginApiVersion(): ?string
    {
        return $this->fields['plugin-api-version'] ?? null;
    }
}
