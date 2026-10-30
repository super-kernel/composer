<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use stdClass;
use SuperKernel\Composer\Exception\LockFileException;
use SuperKernel\Composer\Exception\ManifestException;
use SuperKernel\Composer\Model\Dependency;
use SuperKernel\Composer\Model\EnvironmentRequirement;

use function get_object_vars;
use function is_string;
use function property_exists;
use function sprintf;
use function usort;

/**
 * Validates Composer requirement sections and splits them into package dependencies and environment requirements.
 *
 * @internal
 */
final class RequirementSectionParser
{
    /**
     * @param list<array{string, bool}>                         $sections       Requirement fields with their
     *                                                                          development flags, in order
     * @param class-string<ManifestException|LockFileException> $exceptionClass Exception raised for invalid metadata
     *
     * @return array{list<Dependency>, list<EnvironmentRequirement>} Dependencies and environment requirements, each
     *                                                               sorted by normalized name
     *
     * @throws ManifestException|LockFileException When a requirement section, name, constraint, or duplicate is invalid
     */
    public static function parse(
        stdClass $package,
        string   $packageName,
        string   $context,
        array    $sections,
        string   $exceptionClass,
    ): array
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

        return [
            $dependencies,
            $environmentRequirements,
        ];
    }
}
