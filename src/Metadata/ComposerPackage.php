<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use SuperKernel\Composer\Contract\ComposerAutoloadInterface;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Enum\PackageType;
use SuperKernel\Composer\Exception\ComposerFileException;

use function array_keys;
use function sort;
use function str_starts_with;
use function strtolower;

use const SORT_STRING;

final readonly class ComposerPackage implements ComposerPackageInterface
{
    private PackageType|string $type;

    private ComposerAutoloadInterface $autoload;

    private ComposerAutoloadInterface $autoloadDev;

    /**
     * @param array<string, mixed> $fields      Validated, recursively normalized fields from ComposerFileReader
     * @param bool                 $development Whether the record belongs to composer.lock packages-dev
     * @param bool                 $root        Whether the record represents the user's composer.json
     *
     * @throws ComposerFileException When a non-root record has no name or tries to use the synthetic Root type
     */
    public function __construct(private array $fields, private bool $development = false, bool $root = false)
    {
        $type = $fields['type'] ?? PackageType::Library->value;

        if (!$root && (!isset($fields['name']) || PackageType::Root->value === $type)) {
            throw new ComposerFileException('A lock package must have a name and cannot use the synthetic Root type.');
        }

        $this->type = $root ? PackageType::Root : (PackageType::tryFrom($type) ?? $type);
        $this->autoload = new ComposerAutoload($fields['autoload'] ?? []);
        $this->autoloadDev = new ComposerAutoload($fields['autoload-dev'] ?? []);
    }

    public function type(): PackageType|string
    {
        return $this->type;
    }

    public function name(): ?string
    {
        return isset($this->fields['name']) ? strtolower($this->fields['name']) : null;
    }

    public function isDev(): bool
    {
        return PackageType::Root !== $this->type && $this->development;
    }

    public function extensions(bool $includeDev = true): array
    {
        $requirements = $this->require();

        if ($includeDev) {
            $requirements += $this->requireDev();
        }

        $extensions = [];

        foreach (array_keys($requirements) as $name) {
            $normalized = strtolower((string) $name);

            if (str_starts_with($normalized, 'ext-') && 'ext-' !== $normalized) {
                $extensions[$normalized] = true;
            }
        }

        $names = array_keys($extensions);
        sort($names, SORT_STRING);

        return $names;
    }

    public function autoload(): ComposerAutoloadInterface
    {
        return $this->autoload;
    }

    public function autoloadDev(): ComposerAutoloadInterface
    {
        return $this->autoloadDev;
    }

    public function reference(): ?string
    {
        return $this->fields['dist']['reference'] ?? $this->fields['source']['reference'] ?? null;
    }

    public function version(): ?string
    {
        return $this->fields['version'] ?? null;
    }

    public function metadata(): array
    {
        return $this->fields;
    }

    public function description(): ?string
    {
        return $this->fields['description'] ?? null;
    }

    public function license(): array
    {
        return (array) ($this->fields['license'] ?? []);
    }

    public function keywords(): array
    {
        return $this->fields['keywords'] ?? [];
    }

    public function readme(): ?string
    {
        return $this->fields['readme'] ?? null;
    }

    public function time(): ?string
    {
        return $this->fields['time'] ?? null;
    }

    public function authors(): array
    {
        return $this->fields['authors'] ?? [];
    }

    public function homepage(): ?string
    {
        return $this->fields['homepage'] ?? null;
    }

    public function support(): array
    {
        return $this->fields['support'] ?? [];
    }

    public function funding(): array
    {
        return $this->fields['funding'] ?? [];
    }

    public function source(): ?array
    {
        return $this->fields['source'] ?? null;
    }

    public function dist(): ?array
    {
        return $this->fields['dist'] ?? null;
    }

    public function require(): array
    {
        return $this->fields['require'] ?? [];
    }

    public function requireDev(): array
    {
        return $this->fields['require-dev'] ?? [];
    }

    public function replace(): array
    {
        return $this->fields['replace'] ?? [];
    }

    public function conflict(): array
    {
        return $this->fields['conflict'] ?? [];
    }

    public function provide(): array
    {
        return $this->fields['provide'] ?? [];
    }

    public function suggest(): array
    {
        return $this->fields['suggest'] ?? [];
    }

    public function bin(): array
    {
        return (array) ($this->fields['bin'] ?? []);
    }

    public function extra(): array
    {
        return $this->fields['extra'] ?? [];
    }

    public function abandoned(): bool|string|null
    {
        return $this->fields['abandoned'] ?? null;
    }

    public function defaultBranch(): ?bool
    {
        return $this->fields['default-branch'] ?? null;
    }

    public function normalizedVersion(): ?string
    {
        return $this->fields['version_normalized'] ?? null;
    }

    public function notificationUrl(): ?string
    {
        return $this->fields['notification-url'] ?? null;
    }

    public function installationSource(): ?string
    {
        return $this->fields['installation-source'] ?? null;
    }

    public function transportOptions(): array
    {
        return $this->fields['transport-options'] ?? [];
    }

    public function nonFeatureBranches(): array
    {
        return $this->fields['non-feature-branches'] ?? [];
    }

    public function includePath(): array
    {
        return $this->fields['include-path'] ?? [];
    }

    public function targetDir(): ?string
    {
        return $this->fields['target-dir'] ?? null;
    }

    public function archive(): array
    {
        return $this->fields['archive'] ?? [];
    }

    public function phpExt(): array
    {
        return $this->fields['php-ext'] ?? [];
    }

    public function comment(): array
    {
        return (array) ($this->fields['_comment'] ?? []);
    }

    public function repositories(): array
    {
        return $this->fields['repositories'] ?? [];
    }

    public function minimumStability(): ?string
    {
        return $this->fields['minimum-stability'] ?? null;
    }

    public function preferStable(): ?bool
    {
        return $this->fields['prefer-stable'] ?? null;
    }

    public function config(): array
    {
        return $this->fields['config'] ?? [];
    }

    public function scripts(): array
    {
        return $this->fields['scripts'] ?? [];
    }

    public function scriptsDescriptions(): array
    {
        return $this->fields['scripts-descriptions'] ?? [];
    }

    public function scriptsAliases(): array
    {
        return $this->fields['scripts-aliases'] ?? [];
    }
}
