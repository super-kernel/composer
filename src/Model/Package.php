<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Model;

use InvalidArgumentException;
use SuperKernel\Composer\Contract\DependencyInterface;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;
use SuperKernel\Composer\Enum\AutoloadScope;
use SuperKernel\Composer\Enum\PackageType;
use SuperKernel\Composer\Metadata\ComposerPackageName;
use SuperKernel\Contract\EnvironmentRequirementInterface;

use function array_values;
use function usort;

readonly class Package implements PackageInterface
{
    private PackageType|string $type;

    private string $composerType;

    /** @var list<DependencyInterface> */
    private array $dependencies;

    /** @var list<EnvironmentRequirementInterface> */
    private array $environmentRequirements;

    /**
     * @param list<DependencyInterface>             $dependencies
     * @param list<EnvironmentRequirementInterface> $environmentRequirements
     * @param string|null                           $composerType            Root manifest type; defaults to library
     *
     * @throws InvalidArgumentException When root state or a requirement violates the package contract
     */
    public function __construct(
        private string                $name,
        private ?string               $version,
        private ?string               $reference,
        PackageType|string            $type,
        private bool                  $lockedDevPackage,
        private ?string               $locatedPath,
        private ScanRuleSetInterface  $scanRules,
        array                         $dependencies = [],
        array                         $environmentRequirements = [],
        private ?ScanRuleSetInterface $developmentScanRules = null,
        ?string                       $composerType = null,
    )
    {
        $this->type = $type instanceof PackageType ? $type : (PackageType::tryFrom($type) ?? $type);
        $this->composerType = PackageType::Root === $this->type
            ? ($composerType ?? PackageType::Library->value)
            : ($this->type instanceof PackageType ? $this->type->value : $this->type);

        if (
            PackageType::Root === $this->type
            && (null === $locatedPath || null !== $reference || $lockedDevPackage)
        ) {
            throw new InvalidArgumentException('The root package must have a project path and no lock-derived state.');
        }

        foreach ($dependencies as $dependency) {
            if (!$dependency instanceof DependencyInterface) {
                throw new InvalidArgumentException(
                    'Every package dependency must implement DependencyInterface.',
                );
            }

            if (!ComposerPackageName::isPackage($dependency->name())) {
                throw new InvalidArgumentException(
                    'Every dependency name must identify an installable Composer package.',
                );
            }
        }

        foreach ($environmentRequirements as $requirement) {
            if (!$requirement instanceof EnvironmentRequirementInterface) {
                throw new InvalidArgumentException(
                    'Every environment requirement must implement EnvironmentRequirementInterface.',
                );
            }

            if (!ComposerPackageName::isPlatform($requirement->name())) {
                throw new InvalidArgumentException(
                    'Every environment requirement name must identify a Composer platform package.',
                );
            }

            if ($requirement->requiredBy() !== $name) {
                throw new InvalidArgumentException(
                    'Every environment requirement must be declared by its owning package.',
                );
            }
        }

        usort(
            $dependencies,
            static fn(DependencyInterface $left, DependencyInterface $right): int => [
                    $left->name(),
                    $left->isDevRequirement(),
                    $left->constraint(),
                ] <=> [
                    $right->name(),
                    $right->isDevRequirement(),
                    $right->constraint(),
                ],
        );
        usort(
            $environmentRequirements,
            static fn(EnvironmentRequirementInterface $left, EnvironmentRequirementInterface $right): int => [
                    $left->name(),
                    $left->requiredBy(),
                    $left->isDevRequirement(),
                    $left->constraint(),
                ] <=> [
                    $right->name(),
                    $right->requiredBy(),
                    $right->isDevRequirement(),
                    $right->constraint(),
                ],
        );

        $this->dependencies = array_values($dependencies);
        $this->environmentRequirements = array_values($environmentRequirements);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): PackageType|string
    {
        return $this->type;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function composerType(): string
    {
        return $this->composerType;
    }

    public function isLockedDevPackage(): bool
    {
        return $this->lockedDevPackage;
    }

    public function locatedPath(): ?string
    {
        return $this->locatedPath;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function hasDependencies(): bool
    {
        return [] !== $this->dependencies;
    }

    public function environmentRequirements(): array
    {
        return $this->environmentRequirements;
    }

    public function hasEnvironmentRequirements(): bool
    {
        return [] !== $this->environmentRequirements;
    }

    public function scanRules(): ScanRuleSetInterface
    {
        return $this->scanRules;
    }

    public function scanRulesFor(AutoloadScope $scope): ScanRuleSetInterface
    {
        return PackageType::Root === $this->type && AutoloadScope::Development === $scope
            ? ($this->developmentScanRules ?? $this->scanRules)
            : $this->scanRules;
    }

}
