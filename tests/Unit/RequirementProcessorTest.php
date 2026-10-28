<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use SuperKernel\Composer\Contract\DependencyInterface;
use SuperKernel\Composer\Metadata\RequirementProcessor;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\EnvironmentRequirementInterface;

use function array_map;

final class RequirementProcessorTest extends TestCase
{
    public function testSeparatesRootPackageDependenciesFromEnvironmentRequirements(): void
    {
        $processor = RequirementProcessor::root(
            (object) [
                'require' => (object) [
                    'acme/runtime' => '^1.0',
                    'ext-json'     => '*',
                    'lib-curl'     => '^8.0',
                    'php'          => '^8.4',
                ],
                'require-dev' => (object) [
                    'acme/testing'         => '^2.0',
                    'composer-runtime-api' => '^2.2',
                    'ext-swoole'           => '^6.0',
                ],
            ],
            'acme/project',
        );

        $this->assertSame(
            [
                'acme/runtime',
                'acme/testing',
            ],
            array_map(
                static fn(DependencyInterface $dependency): string => $dependency->name(),
                $processor->dependencies(),
            ),
        );
        $this->assertSame(
            [
                [
                    'composer-runtime-api',
                    '^2.2',
                    'acme/project',
                    true,
                ],
                [
                    'ext-json',
                    '*',
                    'acme/project',
                    false,
                ],
                [
                    'ext-swoole',
                    '^6.0',
                    'acme/project',
                    true,
                ],
                [
                    'lib-curl',
                    '^8.0',
                    'acme/project',
                    false,
                ],
                [
                    'php',
                    '^8.4',
                    'acme/project',
                    false,
                ],
            ],
            $this->environmentRequirementData($processor->environmentRequirements()),
        );
    }

    public function testReadsOnlyRuntimeRequirementsFromLockPackages(): void
    {
        $processor = RequirementProcessor::locked(
            (object) [
                'require' => (object) [
                    'acme/runtime' => '^1.0',
                    'ext-curl'     => '^8.0',
                    'php'          => '^8.1',
                ],
                'require-dev' => [
                    'malformed-but-ignored',
                ],
            ],
            'acme/package',
            'composer.lock packages[0]',
        );

        $this->assertSame(
            [
                'acme/runtime',
            ],
            array_map(
                static fn(DependencyInterface $dependency): string => $dependency->name(),
                $processor->dependencies(),
            ),
        );
        $this->assertSame(
            [
                [
                    'ext-curl',
                    '^8.0',
                    'acme/package',
                    false,
                ],
                [
                    'php',
                    '^8.1',
                    'acme/package',
                    false,
                ],
            ],
            $this->environmentRequirementData($processor->environmentRequirements()),
        );
    }

    /**
     * @param list<EnvironmentRequirementInterface> $requirements
     *
     * @return list<array{string, string, string, bool}>
     */
    private function environmentRequirementData(array $requirements): array
    {
        return array_map(
            static fn(EnvironmentRequirementInterface $requirement): array => [
                $requirement->name(),
                $requirement->constraint(),
                $requirement->requiredBy(),
                $requirement->isDevRequirement(),
            ],
            $requirements,
        );
    }
}
