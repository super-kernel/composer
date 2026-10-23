<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use stdClass;
use SuperKernel\Composer\Contract\DependencyInterface;
use SuperKernel\Composer\Exception\LockFileException;
use SuperKernel\Composer\Exception\ManifestException;
use SuperKernel\Composer\Model\Dependency;
use SuperKernel\Composer\Model\EnvironmentRequirement;
use SuperKernel\Contract\EnvironmentRequirementInterface;

use function get_object_vars;
use function is_string;
use function property_exists;
use function sprintf;
use function usort;

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
        return self::process(
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
        );
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
        return self::process(
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
        );
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

    /**
     * @param list<array{string, bool}>                         $sections
     * @param class-string<ManifestException|LockFileException> $exceptionClass
     *
     * @throws ManifestException|LockFileException When requirement metadata is invalid
     */
    private static function process(
        stdClass $package,
        string   $packageName,
        string   $context,
        array    $sections,
        string   $exceptionClass,
    ): self
    {
        $dependencies = [];
        $environmentRequirements = [];
        $seen = [];

        foreach ($sections as [$field, $dev]) {
            if (!property_exists($package, $field)) {
                continue;
            }

            $requirements = $package->{$field};

            if (!$requirements instanceof stdClass) {
                throw new $exceptionClass(sprintf('%s %s must be an object.', $context, $field));
            }

            foreach (get_object_vars($requirements) as $name => $constraint) {
                if (!ComposerPackageName::isRequirement($name)) {
                    throw new $exceptionClass(sprintf(
                        'Requirement name "%s" in %s %s is invalid or not lowercase.',
                        $name,
                        $context,
                        $field,
                    ));
                }

                if (!is_string($constraint)) {
                    throw new $exceptionClass(sprintf(
                        'Requirement constraint for "%s" in %s %s must be a string.',
                        $name,
                        $context,
                        $field,
                    ));
                }

                if (isset($seen[$name])) {
                    throw new $exceptionClass(sprintf(
                        'Requirement "%s" must not appear in both require and require-dev.',
                        $name,
                    ));
                }

                $seen[$name] = true;

                if (ComposerPackageName::isPlatform($name)) {
                    $environmentRequirements[] = new EnvironmentRequirement(
                        $name,
                        $constraint,
                        $packageName,
                        $dev,
                    );

                    continue;
                }

                $dependencies[] = new Dependency(
                    $name,
                    $constraint,
                    $dev,
                );
            }
        }

        usort(
            $dependencies,
            static fn(Dependency $left, Dependency $right): int => $left->name() <=> $right->name(),
        );
        usort(
            $environmentRequirements,
            static fn(
                EnvironmentRequirement $left,
                EnvironmentRequirement $right,
            ): int => $left->name() <=> $right->name(),
        );

        return new self($dependencies, $environmentRequirements);
    }
}
