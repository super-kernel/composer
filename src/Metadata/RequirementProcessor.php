<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use stdClass;
use SuperKernel\Composer\Contract\DependencyInterface;
use SuperKernel\Composer\Exception\LockFileException;
use SuperKernel\Composer\Exception\ManifestException;
use SuperKernel\Contract\EnvironmentRequirementInterface;

/**
 * Validates Composer requirement sections and separates package dependencies from environment requirements.
 */
final readonly class RequirementProcessor
{
    /**
     * @param list<DependencyInterface>             $dependencies
     * @param list<EnvironmentRequirementInterface> $environmentRequirements
     */
    private function __construct(private array $dependencies, private array $environmentRequirements)
    {
    }

    /**
     * Processes root `require` and `require-dev` declarations.
     *
     * @throws ManifestException When a requirement section, name, constraint, or duplicate is invalid
     */
    public static function root(stdClass $manifest, string $packageName): self
    {
        return new self(...RequirementSectionParser::parse(
            $manifest,
            $packageName,
            'root composer.json',
            [
                [
                    'require',
                    false,
                ],
                [
                    'require-dev',
                    true,
                ],
            ],
            ManifestException::class,
        ));
    }

    /**
     * Processes a lock package's runtime `require` declarations.
     *
     * Dependency-package `require-dev` is deliberately ignored because Composer treats it as root-only.
     *
     * @throws LockFileException When a requirement section, name, or constraint is invalid
     */
    public static function locked(stdClass $package, string $packageName, string $context): self
    {
        return new self(...RequirementSectionParser::parse(
            $package,
            $packageName,
            $context,
            [
                [
                    'require',
                    false,
                ],
            ],
            LockFileException::class,
        ));
    }

    /**
     * @return list<DependencyInterface> Package dependencies sorted by normalized name
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @return list<EnvironmentRequirementInterface> Environment requirements sorted by normalized platform package name
     */
    public function environmentRequirements(): array
    {
        return $this->environmentRequirements;
    }
}
