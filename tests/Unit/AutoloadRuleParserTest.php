<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use SuperKernel\Composer\Constants\AutoloadScope;
use SuperKernel\Composer\Contract\ScanRuleInterface;
use SuperKernel\Composer\Exception\AutoloadRuleException;
use SuperKernel\Composer\Exception\LockFileException;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\Composer\Tests\Support\TestCase;
use Throwable;

use function array_map;
use function implode;
use function in_array;
use function symlink;

final class AutoloadRuleParserTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testBuildsDeterministicRulesWithExplicitRootScope(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            foreach ([
                '/src',
                '/lib',
                '/pear',
                '/class-dir',
                '/addons/one/lib',
                '/tests',
            ] as $directory) {
                $this->makeDirectory($root . $directory);
            }

            $this->writeFile($root . '/class-file.php', '<?php');
            $this->writeFile($root . '/functions.php', '<?php');
            $this->writeProject($root, [
                'name'     => 'acme/project',
                'autoload' => [
                    'psr-4' => [
                        'App\\' => [
                            'src/',
                            'src/',
                            'lib/',
                        ],
                        '' => '',
                    ],
                    'psr-0' => [
                        'Legacy\\'    => 'lib/',
                        'Pear_'       => 'pear/',
                        'UniqueClass' => '',
                        ''            => 'src/',
                    ],
                    'classmap' => [
                        'class-dir/',
                        'class-file.php',
                        'addons/*/lib/',
                        'missing/',
                    ],
                    'files' => [
                        'functions.php',
                    ],
                    'exclude-from-classmap' => [
                        '/Tests/',
                        '**/Test/',
                        'cache/*',
                    ],
                ],
                'autoload-dev' => [
                    'psr-4' => [
                        'Tests\\' => 'tests/',
                    ],
                ],
            ]);

            $rootPackage = $this->readPackages($root)->root();
            $production = $rootPackage->scanRulesFor(AutoloadScope::Production);
            $development = $rootPackage->scanRulesFor(AutoloadScope::Development);
            $keys = $this->ruleKeys($production->inclusions());

            $this->assertSame($keys, $this->ruleKeys($rootPackage->scanRules()->inclusions()));
            $this->assertSame([
                'classmap|<none>|addons/*/lib|glob',
                'classmap|<none>|class-dir|directory',
                'classmap|<none>|class-file.php|file',
                'psr-0||src|directory',
                'psr-0|Legacy\\|lib|directory',
                'psr-0|Pear_|pear|directory',
                'psr-0|UniqueClass|.|directory',
                'psr-4||.|directory',
                'psr-4|App\\|lib|directory',
                'psr-4|App\\|src|directory',
            ], $keys);
            $this->assertCount(11, $development->inclusions());
            $this->assertTrue(in_array(
                'psr-4|Tests\\|tests|directory',
                $this->ruleKeys($development->inclusions()),
                true,
            ));
            $this->assertNotContains(
                'functions.php',
                array_map(
                    static fn(ScanRuleInterface $rule): string => $rule->relativePath(),
                    $production->inclusions(),
                ),
            );

            $this->assertSame([
                '**/Test/',
                '/Tests/',
                'cache/*',
            ], $production->exclusions());
            $this->assertTrue($production->isExcluded('Tests/Example.php'));
            $this->assertFalse($production->isExcluded('src/Tests/Example.php'));
            $this->assertFalse($production->isExcluded('TestsExtra/Example.php'));
            $this->assertFalse($production->isExcluded('Test/Example.php'));
            $this->assertTrue($production->isExcluded('src/Test/Example.php'));
            $this->assertTrue($production->isExcluded('cache/unit/deep/File.php'));
            $this->assertFalse($production->isExcluded('src/cache/unit/File.php'));
            $this->assertTrue($production->isExcluded('src\\Test\\Example.php'));
            $this->assertFalse($production->isExcluded('../Tests/Example.php'));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsUnsafeMalformedAndUnsupportedRootAutoloadDeclarations(): void
    {
        $cases = [
            'PSR-4 prefix' => [
                'autoload' => [
                    'psr-4' => [
                        'MissingSlash' => 'src/',
                    ],
                ],
            ],
            'mapping list' => [
                'autoload' => [
                    'psr-4' => [
                        'Valid\\' => [],
                    ],
                ],
            ],
            'absolute path' => [
                'autoload' => [
                    'psr-4' => [
                        'Valid\\' => '/tmp/src',
                    ],
                ],
            ],
            'Windows absolute path' => [
                'autoload' => [
                    'psr-4' => [
                        'Valid\\' => 'C:\\tmp\\src',
                    ],
                ],
            ],
            'parent path' => [
                'autoload' => [
                    'classmap' => [
                        '../outside',
                    ],
                ],
            ],
            'null byte' => [
                'autoload' => [
                    'files' => [
                        "safe\0unsafe.php",
                    ],
                ],
            ],
            'unsupported wildcard' => [
                'autoload' => [
                    'classmap' => [
                        'src/?/lib',
                    ],
                ],
            ],
            'unsafe exclusion' => [
                'autoload' => [
                    'exclude-from-classmap' => [
                        '../outside',
                    ],
                ],
            ],
            'Windows absolute exclusion' => [
                'autoload' => [
                    'exclude-from-classmap' => [
                        '/C:/outside',
                    ],
                ],
            ],
            'target-dir' => [
                'target-dir' => 'Legacy',
            ],
            'include-path' => [
                'include-path' => [
                    'legacy/',
                ],
            ],
        ];

        foreach ($cases as $label => $fragment) {
            $this->withTemporaryDirectory(function (
                string $root,
            ) use ($fragment, $label): void {
                $this->makeDirectory($root . '/src');
                $this->writeProject($root, [
                    'name' => 'acme/project',
                    ...$fragment,
                ]);

                $this->assertThrows(
                    AutoloadRuleException::class,
                    fn() => $this->readPackages($root),
                    messageContains: 'PSR-4 prefix' === $label ? 'must end in a backslash' : null,
                );
            });
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testResolvesAutoloadFilesInDependencyOrderWithoutAddingScanRules(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeFile($root . '/root-functions.php', '<?php');
            $this->writeFile($root . '/dev-functions.php', '<?php');
            $this->writeFile($root . '/vendor/acme/base/base-functions.php', '<?php');
            $this->writeFile($root . '/vendor/acme/dependent/dependent-functions.php', '<?php');
            $this->writeProject($root, [
                'name'    => 'acme/project',
                'require' => [
                    'acme/dependent' => '^1.0',
                ],
                'autoload' => [
                    'files' => [
                        'root-functions.php',
                    ],
                ],
                'autoload-dev' => [
                    'files' => [
                        'dev-functions.php',
                    ],
                ],
            ], [
                'packages' => [
                    [
                        'name'    => 'acme/dependent',
                        'version' => '1.0.0',
                        'require' => [
                            'acme/base' => '^1.0',
                        ],
                        'autoload' => [
                            'files' => [
                                'dependent-functions.php',
                            ],
                        ],
                    ],
                    [
                        'name'     => 'acme/base',
                        'version'  => '1.0.0',
                        'autoload' => [
                            'files' => [
                                'base-functions.php',
                            ],
                        ],
                    ],
                ],
                'packages-dev' => [],
            ]);

            $metadata = $this->readPackages($root);

            $this->assertSame([
                $root . '/vendor/acme/base/base-functions.php',
                $root . '/vendor/acme/dependent/dependent-functions.php',
                $root . '/root-functions.php',
                $root . '/dev-functions.php',
            ], $metadata->autoloadFiles());
            $this->assertSame([], $metadata->root()->scanRules()->inclusions());
            $this->assertSame([], $metadata->get('acme/base')?->scanRules()->inclusions());
            $this->assertSame([], $metadata->get('acme/dependent')?->scanRules()->inclusions());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsSymlinkEscapesForDirectPathsAndClassmapGlobs(): void
    {
        foreach ([
            'escape',
            'glob/*',
        ] as $declaredPath) {
            $this->withTemporaryDirectory(function (
                string $workspace,
            ) use ($declaredPath): void {
                $root = $workspace . '/project';
                $outside = $workspace . '/outside';
                $this->makeDirectory($root);
                $this->makeDirectory($outside);

                if ('glob/*' === $declaredPath) {
                    $this->makeDirectory($root . '/glob');
                    $this->assertTrue(symlink($outside, $root . '/glob/outside'));
                } else {
                    $this->assertTrue(symlink($outside, $root . '/escape'));
                }

                $this->writeProject($root, [
                    'name'     => 'acme/project',
                    'autoload' => [
                        'classmap' => [
                            $declaredPath,
                        ],
                    ],
                ]);

                $this->assertThrows(
                    AutoloadRuleException::class,
                    fn() => $this->readPackages($root),
                    messageContains: 'outside the package root',
                );
            });
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testValidatesLockedAutoloadMetadataAndRejectsMissingPackageDirectories(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->makeDirectory($root . '/vendor/acme/invalid');
            $this->writeProject($root, [
                'name' => 'acme/project',
            ], [
                'packages' => [
                    [
                    'name'     => 'acme/invalid',
                    'version'  => '1.0.0',
                    'autoload' => [
                        'psr-4' => [
                            'Acme\\Invalid\\' => '../outside',
                        ],
                    ],
                ],
                ],
                'packages-dev' => [],
            ]);

            $this->assertThrows(
                LockFileException::class,
                fn() => $this->readPackages($root),
                messageContains: 'acme/invalid',
            );
        });

        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeProject($root, [
                'name' => 'acme/project',
            ], [
                'packages' => [
                    [
                    'name'     => 'acme/missing',
                    'version'  => '1.0.0',
                    'autoload' => [
                        'psr-4' => [
                            'Acme\\Missing\\' => 'src/',
                        ],
                        'exclude-from-classmap' => [
                            '/Tests/',
                        ],
                    ],
                ],
                ],
                'packages-dev' => [],
            ]);

            $this->assertThrows(
                PackageLocationException::class,
                fn() => $this->readPackages($root),
                messageContains: 'acme/missing',
            );
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testIgnoresDependencyAutoloadDevButRejectsLegacyLockFields(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->makeDirectory($root . '/vendor/acme/dependency/src');
            $this->writeProject($root, [
                'name' => 'acme/project',
            ], [
                'packages' => [
                    [
                    'name'     => 'acme/dependency',
                    'version'  => '1.0.0',
                    'autoload' => [
                        'psr-4' => [
                            'Acme\\Dependency\\' => 'src/',
                        ],
                    ],
                    'autoload-dev' => [
                        'psr-4' => [
                            'Broken\\' => '/absolute/path',
                        ],
                    ],
                ],
                ],
                'packages-dev' => [],
            ]);

            $package = $this->readPackages($root)->get('acme/dependency');
            $this->assertCount(1, $package?->scanRules()->inclusions() ?? []);
        });

        foreach ([
            'target-dir',
            'include-path',
        ] as $field) {
            $this->withTemporaryDirectory(function (
                string $root,
            ) use ($field): void {
                $this->makeDirectory($root . '/vendor/acme/legacy');
                $this->writeProject($root, [
                    'name' => 'acme/project',
                ], [
                    'packages' => [
                        [
                        'name'    => 'acme/legacy',
                        'version' => '1.0.0',
                        $field    => 'include-path' === $field ? [
                            'legacy/',
                        ] : 'legacy/',
                    ],
                    ],
                    'packages-dev' => [],
                ]);

                $this->assertThrows(
                    LockFileException::class,
                    fn() => $this->readPackages($root),
                    messageContains: $field,
                );
            });
        }
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $lock
     *
     * @throws Throwable When fixture setup or a supplied test callback fails
     */
    private function writeProject(string $root, array $manifest, array $lock = []): void
    {
        $this->writeJson($root . '/composer.json', $manifest);
        $this->writeJson($root . '/composer.lock', [
            'content-hash' => '0123456789abcdef0123456789abcdef',
            ...([] !== $lock ? $lock : [
                'packages'     => [],
                'packages-dev' => [],
            ]),
        ]);
    }

    /**
     * @param list<ScanRuleInterface> $rules
     *
     * @return list<string>
     */
    private function ruleKeys(array $rules): array
    {
        return array_map(
            static fn(ScanRuleInterface $rule): string => implode('|', [
                $rule->source()->value,
                $rule->namespacePrefix() ?? '<none>',
                $rule->relativePath(),
                $rule->kind()->value,
            ]),
            $rules,
        );
    }
}
