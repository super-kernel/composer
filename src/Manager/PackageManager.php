<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Manager;

use InvalidArgumentException;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Enum\PackageType;
use SuperKernel\Contract\EnvironmentRequirementInterface;
use SuperKernel\Contract\PackageManagerInterface;

use function array_filter;
use function array_is_list;
use function array_keys;
use function array_values;
use function is_array;
use function is_string;
use function ksort;
use function sprintf;
use function str_contains;
use function strtolower;
use function usort;

use const SORT_STRING;

/**
 * Indexes prepared package metadata and class graphs for one Composer vendor workspace.
 */
final readonly class PackageManager implements PackageManagerInterface
{
    /** @var list<PackageInterface> */
    private array $all;

    /** @var list<PackageInterface> */
    private array $packages;

    /** @var array<string, PackageInterface> */
    private array $byName;

    /** @var list<EnvironmentRequirementInterface> */
    private array $environmentRequirements;

    /** @var array<string, array<class-string, non-empty-string>> */
    private array $classesByPackage;

    /** @var array<class-string, non-empty-string> */
    private array $allClasses;

    /** @var array<class-string, PackageInterface> */
    private array $packageByClass;

    /** @var list<non-empty-string> */
    private array $autoloadFiles;

    /**
     * @param list<PackageInterface>                               $packages         Lock-file packages to index
     * @param array<string, array<class-string, non-empty-string>> $classesByPackage Class maps keyed by package name
     * @param list<non-empty-string>                               $autoloadFiles    Paths in dependency order
     *
     * @throws InvalidArgumentException When package, class-map, or file-autoload data violates the manager contract
     */
    public function __construct(
        private PackageInterface $rootPackage,
        array                    $packages,
        array                    $classesByPackage = [],
        array                    $autoloadFiles = [],
    )
    {
        if (PackageType::Root !== $rootPackage->type()) {
            throw new InvalidArgumentException('The root package must have the Root package type.');
        }

        foreach ($packages as $package) {
            if (!$package instanceof PackageInterface) {
                throw new InvalidArgumentException('Every managed package must implement PackageInterface.');
            }

            if (PackageType::Root === $package->type()) {
                throw new InvalidArgumentException('Lock-file packages must not have the Root package type.');
            }
        }

        usort(
            $packages,
            static fn(PackageInterface $left, PackageInterface $right): int => $left->name() <=> $right->name(),
        );

        $byName = [
            $rootPackage->name() => $rootPackage,
        ];
        $environmentRequirements = $rootPackage->environmentRequirements();

        foreach ($packages as $package) {
            if (isset($byName[$package->name()])) {
                throw new InvalidArgumentException(sprintf(
                    'Package name "%s" occurs more than once.',
                    $package->name(),
                ));
            }

            $byName[$package->name()] = $package;

            foreach ($package->environmentRequirements() as $requirement) {
                $environmentRequirements[] = $requirement;
            }
        }

        $normalizedProvidedClassMaps = [];

        foreach ($classesByPackage as $packageName => $classMap) {
            $normalizedPackageName = is_string($packageName) ? strtolower($packageName) : null;

            if (null === $normalizedPackageName || !isset($byName[$normalizedPackageName])) {
                throw new InvalidArgumentException('Class maps must be keyed by a managed package name.');
            }

            if (!is_array($classMap)) {
                throw new InvalidArgumentException('Every package class map must be an array.');
            }

            if (isset($normalizedProvidedClassMaps[$normalizedPackageName])) {
                throw new InvalidArgumentException(sprintf(
                    'A class map for package "%s" occurs more than once.',
                    $normalizedPackageName,
                ));
            }

            $normalizedProvidedClassMaps[$normalizedPackageName] = $classMap;
        }

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

        $normalizedClassMaps = [];
        $allClasses = [];
        $packageByClass = [];

        foreach ($byName as $packageName => $package) {
            $classMap = $normalizedProvidedClassMaps[$packageName] ?? [];

            foreach ($classMap as $class => $file) {
                if (
                    !is_string($class)
                    || '' === $class
                    || str_contains($class, "\0")
                    || !is_string($file)
                    || '' === $file
                    || str_contains($file, "\0")
                ) {
                    throw new InvalidArgumentException(
                        'Class maps must contain non-empty string class and path pairs.',
                    );
                }

                if (!isset($allClasses[$class])) {
                    $allClasses[$class] = $file;
                    $packageByClass[$class] = $package;
                }
            }

            ksort($classMap, SORT_STRING);
            $normalizedClassMaps[$packageName] = $classMap;
        }

        ksort($allClasses, SORT_STRING);

        if (!array_is_list($autoloadFiles)) {
            throw new InvalidArgumentException('Autoload files must be a list.');
        }

        $normalizedAutoloadFiles = [];

        foreach ($autoloadFiles as $file) {
            if (!is_string($file) || '' === $file || str_contains($file, "\0")) {
                throw new InvalidArgumentException('Autoload files must contain only non-empty string paths.');
            }

            $normalizedAutoloadFiles[$file] = true;
        }

        $this->packages = array_values($packages);
        $this->all = [
            $rootPackage,
            ...$this->packages,
        ];
        $this->byName = $byName;
        $this->environmentRequirements = array_values($environmentRequirements);
        $this->classesByPackage = $normalizedClassMaps;
        $this->allClasses = $allClasses;
        $this->packageByClass = $packageByClass;
        $this->autoloadFiles = array_keys($normalizedAutoloadFiles);
    }

    /** @return list<PackageInterface> */
    public function all(): array
    {
        return $this->all;
    }

    /** @return list<PackageInterface> */
    public function packages(): array
    {
        return $this->packages;
    }

    public function environmentRequirements(): array
    {
        return $this->environmentRequirements;
    }

    public function hasEnvironmentRequirements(): bool
    {
        return [] !== $this->environmentRequirements;
    }

    /** @return list<PackageInterface> */
    public function ofComposerType(string $composerType): array
    {
        return array_values(array_filter(
            $this->all,
            static fn(PackageInterface $package): bool => $package->composerType() === $composerType,
        ));
    }

    public function get(string $name): ?PackageInterface
    {
        return $this->byName[strtolower($name)] ?? null;
    }

    public function root(): PackageInterface
    {
        return $this->rootPackage;
    }

    public function classes(string $packageName): ?array
    {
        $normalizedName = strtolower($packageName);

        return isset($this->byName[$normalizedName])
            ? $this->classesByPackage[$normalizedName]
            : null;
    }

    public function allClasses(): array
    {
        return $this->allClasses;
    }

    public function packageForClass(string $class): ?PackageInterface
    {
        return $this->packageByClass[$class] ?? null;
    }

    /**
     * Returns canonical file-autoload paths in dependency-before-dependent order.
     *
     * @return list<non-empty-string>
     *
     * @internal
     */
    public function autoloadFiles(): array
    {
        return $this->autoloadFiles;
    }

    /**
     * Returns the same package metadata with a replacement set of derived class maps.
     *
     * @param array<string, array<class-string, non-empty-string>> $classesByPackage
     */
    public function withClassMaps(array $classesByPackage): self
    {
        return new self(
            $this->rootPackage,
            $this->packages,
            $classesByPackage,
            $this->autoloadFiles,
        );
    }
}
