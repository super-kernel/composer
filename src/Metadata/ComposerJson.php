<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use SuperKernel\Composer\Contract\ComposerJsonInterface;

final readonly class ComposerJson implements ComposerJsonInterface
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

    public function name(): ?string
    {
        return $this->fields['name'] ?? null;
    }

    public function description(): ?string
    {
        return $this->fields['description'] ?? null;
    }

    public function license(): array|string|null
    {
        return $this->fields['license'] ?? null;
    }

    public function type(): ?string
    {
        return $this->fields['type'] ?? null;
    }

    public function abandoned(): bool|string|null
    {
        return $this->fields['abandoned'] ?? null;
    }

    public function version(): ?string
    {
        return $this->fields['version'] ?? null;
    }

    public function defaultBranch(): ?bool
    {
        return $this->fields['default-branch'] ?? null;
    }

    public function nonFeatureBranches(): ?array
    {
        return $this->fields['non-feature-branches'] ?? null;
    }

    public function keywords(): ?array
    {
        return $this->fields['keywords'] ?? null;
    }

    public function readme(): ?string
    {
        return $this->fields['readme'] ?? null;
    }

    public function time(): ?string
    {
        return $this->fields['time'] ?? null;
    }

    public function authors(): ?array
    {
        return $this->fields['authors'] ?? null;
    }

    public function homepage(): ?string
    {
        return $this->fields['homepage'] ?? null;
    }

    public function support(): ?array
    {
        return $this->fields['support'] ?? null;
    }

    public function funding(): ?array
    {
        return $this->fields['funding'] ?? null;
    }

    public function source(): ?array
    {
        return $this->fields['source'] ?? null;
    }

    public function dist(): ?array
    {
        return $this->fields['dist'] ?? null;
    }

    public function comment(): array|string|null
    {
        return $this->fields['_comment'] ?? null;
    }

    public function require(): ?array
    {
        return $this->fields['require'] ?? null;
    }

    public function requireDev(): ?array
    {
        return $this->fields['require-dev'] ?? null;
    }

    public function replace(): ?array
    {
        return $this->fields['replace'] ?? null;
    }

    public function conflict(): ?array
    {
        return $this->fields['conflict'] ?? null;
    }

    public function provide(): ?array
    {
        return $this->fields['provide'] ?? null;
    }

    public function suggest(): ?array
    {
        return $this->fields['suggest'] ?? null;
    }

    public function repositories(): ?array
    {
        return $this->fields['repositories'] ?? null;
    }

    public function minimumStability(): ?string
    {
        return $this->fields['minimum-stability'] ?? null;
    }

    public function preferStable(): ?bool
    {
        return $this->fields['prefer-stable'] ?? null;
    }

    public function autoload(): ?array
    {
        return $this->fields['autoload'] ?? null;
    }

    public function autoloadDev(): ?array
    {
        return $this->fields['autoload-dev'] ?? null;
    }

    public function targetDir(): ?string
    {
        return $this->fields['target-dir'] ?? null;
    }

    public function includePath(): ?array
    {
        return $this->fields['include-path'] ?? null;
    }

    public function bin(): array|string|null
    {
        return $this->fields['bin'] ?? null;
    }

    public function archive(): ?array
    {
        return $this->fields['archive'] ?? null;
    }

    public function phpExt(): ?array
    {
        return $this->fields['php-ext'] ?? null;
    }

    public function config(): ?array
    {
        return $this->fields['config'] ?? null;
    }

    public function extra(): ?array
    {
        return $this->fields['extra'] ?? null;
    }

    public function scripts(): ?array
    {
        return $this->fields['scripts'] ?? null;
    }

    public function scriptsDescriptions(): ?array
    {
        return $this->fields['scripts-descriptions'] ?? null;
    }

    public function scriptsAliases(): ?array
    {
        return $this->fields['scripts-aliases'] ?? null;
    }
}
