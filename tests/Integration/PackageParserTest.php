<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use SuperKernel\Composer\Contract\DependencyInterface;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\PackageLocatorInterface;
use SuperKernel\Composer\Enum\AutoloadScope;
use SuperKernel\Composer\Enum\PackageType;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Exception\LockFileException;
use SuperKernel\Composer\Exception\ManifestException;
use SuperKernel\Composer\Model\Package;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\EnvironmentRequirementInterface;
use Throwable;

use function array_diff_key;
use function array_filter;
use function array_find;
use function array_map;
use function array_values;
use function is_string;
use function realpath;
use function unlink;

final class PackageParserTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testModelsPackageDependenciesEnvironmentRequirementsAndLockedPackagesWithoutConflatingState(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            foreach ([
                '/src',
                '/tests',
                '/vendor/acme/prod/src',
                '/vendor/acme/dev/lib',
            ] as $directory) {
                $this->makeDirectory($root . $directory);
            }

            $this->writeFile($root . '/functions.php', '<?php');
            $this->writeFile($root . '/src/Service.source', '<?php namespace App; final class Service {}');
            $this->writeFile(
                $root . '/vendor/acme/prod/src/Feature.source',
                '<?php namespace Acme\\Prod; final class Feature {}',
            );
            $this->writeProject($root, $this->validManifest(), $this->validLock());

            $packages = $this->readPackages($root);
            $rootPackage = $packages->root();
            $dependencies = $rootPackage->dependencies();

            $this->assertSame(
                [
                    'acme/dev',
                    'acme/prod',
                ],
                $this->names($dependencies),
            );
            $this->assertSame(
                [
                    'acme/prod',
                ],
                $this->names(array_values(array_filter(
                    $dependencies,
                    static fn(DependencyInterface $dependency): bool => !$dependency->isDevRequirement(),
                ))),
            );
            $this->assertSame(
                [
                    'acme/dev',
                ],
                $this->names(array_values(array_filter(
                    $dependencies,
                    static fn(DependencyInterface $dependency): bool => $dependency->isDevRequirement(),
                ))),
            );
            $this->assertSame('^1.0', $this->dependency($dependencies, 'acme/prod')?->constraint());
            $this->assertNull($this->dependency($dependencies, 'php'));
            $this->assertNull($this->dependency($dependencies, 'ext-json'));
            $this->assertNull($this->dependency($dependencies, 'ext-swoole'));
            $this->assertNull($this->dependency($dependencies, 'composer-runtime-api'));

            $this->assertSame(Package::class, $rootPackage::class);
            $this->assertSame(PackageType::Root, $rootPackage->type());
            $this->assertSame(
                [
                    'vendor/application',
                    'acme/dev',
                    'acme/meta',
                    'acme/prod',
                ],
                $this->names($packages->all()),
            );
            $this->assertSame([
                'acme/dev',
                'acme/meta',
                'acme/prod',
            ], $this->names($packages->packages()));
            $this->assertSame('vendor/application', $rootPackage->name());
            $this->assertNull($rootPackage->version());
            $this->assertNull($rootPackage->reference());
            $this->assertSame('project', $rootPackage->composerType());
            $this->assertFalse($rootPackage->isLockedDevPackage());
            $this->assertSame(realpath($root), $rootPackage->locatedPath());
            $this->assertCount(1, $rootPackage->scanRules()->inclusions());
            $this->assertCount(2, $rootPackage->scanRulesFor(AutoloadScope::Development)->inclusions());
            $this->assertTrue($rootPackage->hasDependencies());
            $this->assertSame([
                'acme/dev',
                'acme/prod',
            ], $this->names($rootPackage->dependencies()));
            $this->assertTrue($rootPackage->hasEnvironmentRequirements());
            $this->assertSame(
                [
                    [
                        'composer-runtime-api',
                        '^2.0',
                        'vendor/application',
                        true,
                    ],
                    [
                        'ext-json',
                        '*',
                        'vendor/application',
                        false,
                    ],
                    [
                        'ext-swoole',
                        '^6.0',
                        'vendor/application',
                        true,
                    ],
                    [
                        'php',
                        '^8.4',
                        'vendor/application',
                        false,
                    ],
                ],
                $this->environmentRequirementData($rootPackage->environmentRequirements()),
            );

            $productionPackage = $packages->get('ACME/PROD');
            $this->assertNotNull($productionPackage);
            $this->assertSame($rootPackage::class, $productionPackage::class);
            $this->assertSame(PackageType::Library, $productionPackage->type());
            $this->assertSame('1.2.3', $productionPackage->version());
            $this->assertSame('prod-dist-reference', $productionPackage->reference());
            $this->assertSame('library', $productionPackage->composerType());
            $this->assertFalse($productionPackage->isLockedDevPackage());
            $this->assertSame(realpath($root . '/vendor/acme/prod'), $productionPackage->locatedPath());
            $this->assertCount(1, $productionPackage->scanRules()->inclusions());
            $this->assertSame(
                $productionPackage->scanRules(),
                $productionPackage->scanRulesFor(AutoloadScope::Development),
            );
            $this->assertTrue($productionPackage->hasDependencies());
            $this->assertSame([
                'acme/shared',
            ], $this->names($productionPackage->dependencies()));
            $this->assertTrue($productionPackage->hasEnvironmentRequirements());
            $this->assertSame(
                [
                    [
                        'ext-curl',
                        '^8.0',
                        'acme/prod',
                        false,
                    ],
                    [
                        'ext-json',
                        '*',
                        'acme/prod',
                        false,
                    ],
                    [
                        'lib-curl',
                        '^8.0',
                        'acme/prod',
                        false,
                    ],
                    [
                        'php',
                        '^8.1',
                        'acme/prod',
                        false,
                    ],
                ],
                $this->environmentRequirementData($productionPackage->environmentRequirements()),
            );

            $developmentPackage = $packages->get('acme/dev');
            $this->assertNotNull($developmentPackage);
            $this->assertSame('dev-source-reference', $developmentPackage->reference());
            $this->assertTrue($developmentPackage->isLockedDevPackage());
            $this->assertSame('custom-installer', $developmentPackage->composerType());
            $this->assertSame('custom-installer', $developmentPackage->type());
            $this->assertSame(realpath($root . '/vendor/acme/dev'), $developmentPackage->locatedPath());
            $this->assertCount(1, $developmentPackage->scanRules()->inclusions());
            $this->assertTrue($developmentPackage->hasDependencies());
            $this->assertSame([
                'acme/dev-helper',
            ], $this->names($developmentPackage->dependencies()));
            $this->assertTrue($developmentPackage->hasEnvironmentRequirements());
            $this->assertSame(
                [
                    [
                        'ext-redis',
                        '*',
                        'acme/dev',
                        false,
                    ],
                ],
                $this->environmentRequirementData($developmentPackage->environmentRequirements()),
            );

            $metapackage = $packages->get('acme/meta');
            $this->assertNotNull($metapackage);
            $this->assertSame(PackageType::Metapackage, $metapackage->type());
            $this->assertNull($metapackage->reference());
            $this->assertFalse($metapackage->hasDependencies());
            $this->assertSame([], $metapackage->dependencies());
            $this->assertFalse($metapackage->hasEnvironmentRequirements());
            $this->assertSame([], $metapackage->environmentRequirements());

            $this->assertTrue($packages->hasEnvironmentRequirements());
            $this->assertSame(
                [
                    [
                        'composer-runtime-api',
                        '^2.0',
                        'vendor/application',
                        true,
                    ],
                    [
                        'ext-curl',
                        '^8.0',
                        'acme/prod',
                        false,
                    ],
                    [
                        'ext-json',
                        '*',
                        'acme/prod',
                        false,
                    ],
                    [
                        'ext-json',
                        '*',
                        'vendor/application',
                        false,
                    ],
                    [
                        'ext-redis',
                        '*',
                        'acme/dev',
                        false,
                    ],
                    [
                        'ext-swoole',
                        '^6.0',
                        'vendor/application',
                        true,
                    ],
                    [
                        'lib-curl',
                        '^8.0',
                        'acme/prod',
                        false,
                    ],
                    [
                        'php',
                        '^8.1',
                        'acme/prod',
                        false,
                    ],
                    [
                        'php',
                        '^8.4',
                        'vendor/application',
                        false,
                    ],
                ],
                $this->environmentRequirementData($packages->environmentRequirements()),
            );

            $this->assertNull($packages->get('psr/example-implementation'));
            $this->assertNull($packages->get('missing/package'));
            $this->assertSame([
                'acme/prod',
            ], $this->names($packages->ofComposerType('library')));
            $this->assertSame([
                'acme/meta',
            ], $this->names($packages->ofComposerType('metapackage')));
            $this->assertSame(
                [
                    'App\\Service' => realpath($root . '/src/Service.source'),
                ],
                $packages->classes('VENDOR/APPLICATION'),
            );
            $this->assertSame(
                [
                    'Acme\\Prod\\Feature' => realpath($root . '/vendor/acme/prod/src/Feature.source'),
                ],
                $packages->classes('acme/prod'),
            );
            $this->assertNull($packages->classes('missing/package'));
            $this->assertSame(
                [
                    'Acme\\Prod\\Feature' => realpath($root . '/vendor/acme/prod/src/Feature.source'),
                    'App\\Service'        => realpath($root . '/src/Service.source'),
                ],
                $packages->allClasses(),
            );
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testOwnPackageUsesItsLockedRequirementsAndAutoloadDeclarations(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $directory = $root . '/vendor/super-kernel/composer';
            $source = $directory . '/lib/LockedService.php';
            $autoloadFile = $directory . '/functions.php';
            $this->writeFile($source, '<?php namespace Locked\\Composer; final class LockedService {}');
            $this->writeFile(
                $autoloadFile,
                '<?php throw new RuntimeException("Autoload files must stay declarative.");',
            );
            $this->writeJson($directory . '/composer.json', [
                'name' => 'ignored/component-manifest',
            ]);
            $this->writeProject($root, [
                'name' => 'acme/project',
            ], [
                'packages' => [
                    [
                        'name'    => 'acme/runtime',
                        'version' => '2.0.0',
                        'type'    => 'metapackage',
                    ],
                ],
                'packages-dev' => [
                    [
                        'name'    => 'super-kernel/composer',
                        'version' => '2.4.0',
                        'type'    => 'custom-library',
                        'dist'    => [
                            'type'      => 'path',
                            'url'       => $directory,
                            'reference' => 'locked-self-reference',
                        ],
                        'require' => [
                            'acme/runtime' => '^2.0',
                            'php'          => '^8.4',
                        ],
                        'require-dev' => [
                            'ignored/development' => '*',
                        ],
                        'autoload' => [
                            'psr-4' => [
                                'Locked\\Composer\\' => 'lib/',
                            ],
                            'files' => [
                                'functions.php',
                            ],
                        ],
                        'autoload-dev' => [
                            'files' => [
                                'missing-development.php',
                            ],
                        ],
                    ],
                ],
            ]);

            $packages = $this->readPackages($root);
            $package = $packages->get('super-kernel/composer');

            self::assertSame(Package::class, $package::class);
            self::assertSame('2.4.0', $package->version());
            self::assertSame('locked-self-reference', $package->reference());
            self::assertSame('custom-library', $package->type());
            self::assertSame('custom-library', $package->composerType());
            self::assertTrue($package->isLockedDevPackage());
            self::assertSame($directory, $package->locatedPath());
            self::assertSame([
                'acme/runtime',
            ], $this->names($package->dependencies()));
            self::assertSame('^2.0', $package->dependencies()[0]->constraint());
            self::assertFalse($package->dependencies()[0]->isDevRequirement());
            self::assertSame([
                [
                    'php',
                    '^8.4',
                    'super-kernel/composer',
                    false,
                ],
            ], $this->environmentRequirementData($package->environmentRequirements()));
            self::assertSame('lib', $package->scanRules()->inclusions()[0]->relativePath());
            self::assertSame('Locked\\Composer\\', $package->scanRules()->inclusions()[0]->namespacePrefix());
            self::assertSame([
                'Locked\\Composer\\LockedService' => $source,
            ], $packages->classes('super-kernel/composer'));
            self::assertSame([
                $autoloadFile,
            ], $packages->autoloadFiles());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testOwnPackageCannotBypassLockedAutoloadValidation(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->makeDirectory($root . '/vendor/super-kernel/composer');
            $this->writeProject($root, [
                'name' => 'acme/project',
            ], [
                'packages' => [
                    [
                        'name'     => 'super-kernel/composer',
                        'version'  => 'dev-main',
                        'autoload' => [
                            'classmap' => [
                                '../outside',
                            ],
                        ],
                    ],
                ],
                'packages-dev' => [],
            ]);

            $this->assertThrows(LockFileException::class, fn() => $this->readPackages($root));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPreservesAnExplicitRootDevVersionWithoutMarkingTheRootAsLockedDev(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $manifest = $this->validManifest();
            $manifest['version'] = 'dev-feature';
            $manifest['type'] = 'custom-project';
            $this->writeProject($root, $manifest, [
                'packages'     => [],
                'packages-dev' => [],
            ]);

            $rootPackage = $this->readPackages($root)->root();

            $this->assertSame('dev-feature', $rootPackage->version());
            $this->assertSame(PackageType::Root, $rootPackage->type());
            $this->assertSame('custom-project', $rootPackage->composerType());
            $this->assertFalse($rootPackage->isLockedDevPackage());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDefaultsDependenciesAndEnvironmentRequirementsToEmpty(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeProject(
                $root,
                [
                    'name' => 'vendor/application',
                ],
                [
                    'packages'     => [],
                    'packages-dev' => [],
                ],
            );

            $metadata = $this->readPackages($root);
            $rootPackage = $metadata->root();

            $this->assertSame([], $rootPackage->dependencies());
            $this->assertFalse($rootPackage->hasDependencies());
            $this->assertSame([], $rootPackage->environmentRequirements());
            $this->assertFalse($rootPackage->hasEnvironmentRequirements());
            $this->assertSame([], $metadata->environmentRequirements());
            $this->assertFalse($metadata->hasEnvironmentRequirements());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsInvalidRootsManifestsAndDirectDependencyStructures(): void
    {
        $this->assertThrows(
            ComposerFileException::class,
            fn() => $this->readPackages('/definitely/missing/super-kernel-project'),
        );

        $cases = [
            'missing manifest' => null,
            'malformed JSON'   => '{',
            'missing name'     => array_diff_key($this->validManifest(), [
                'name' => true,
            ]),
            'uppercase name' => [
                ...$this->validManifest(),
                'name' => 'Vendor/Application',
            ],
            'invalid name' => [
                ...$this->validManifest(),
                'name' => 'super-kernel',
            ],
            'non-string version' => [
                ...$this->validManifest(),
                'version' => 123,
            ],
            'invalid type' => [
                ...$this->validManifest(),
                'type' => 'Custom_Type',
            ],
            'non-object requirements' => [
                ...$this->validManifest(),
                'require' => [
                    'not-a-list-item',
                ],
            ],
            'duplicate requirements' => [
                ...$this->validManifest(),
                'require-dev' => [
                    'ext-json' => '*',
                ],
            ],
            'invalid extension constraint' => [
                ...$this->validManifest(),
                'require' => [
                    'ext-json' => false,
                ],
            ],
        ];

        foreach ($cases as $label => $manifest) {
            $this->withTemporaryDirectory(function (
                string $root,
            ) use ($manifest, $label): void {
                if (null === $manifest) {
                    $this->writeJson($root . '/composer.lock', [
                'content-hash'         => '0123456789abcdef0123456789abcdef',
                        'packages'     => [],
                        'packages-dev' => [],
                    ]);
                } elseif (is_string($manifest)) {
                    $this->writeFile($root . '/composer.json', $manifest);
                    $this->writeJson($root . '/composer.lock', [
                'content-hash'         => '0123456789abcdef0123456789abcdef',
                        'packages'     => [],
                        'packages-dev' => [],
                    ]);
                } else {
                    $this->writeProject($root, $manifest, [
                        'packages'     => [],
                        'packages-dev' => [],
                    ]);
                }

                $this->assertThrows(
                    ('missing name' === $label || 'duplicate requirements' === $label)
                        ? ManifestException::class : ComposerFileException::class,
                    fn() => $this->readPackages($root),
                    messageContains: 'duplicate requirements' === $label ? 'both require and require-dev' : null,
                );
            });
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRequiresAStructurallyValidLockAndRejectsPackageNameCollisions(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeJson($root . '/composer.json', $this->validManifest());
            $this->assertThrows(
                ComposerFileException::class,
                fn() => $this->readPackages($root),
            );
        });

        $invalidLocks = [
            'malformed'            => '{',
            'missing packages-dev' => [
                'packages' => [],
            ],
            'non-object entry' => [
                'packages' => [
                    'invalid',
                ],
                'packages-dev' => [],
            ],
            'missing version' => [
                'packages' => [
                    [
                        'name' => 'acme/package',
                    ],
                ],
                'packages-dev' => [],
            ],
            'duplicate arrays' => [
                'packages' => [
                    [
                        'name'    => 'acme/package',
                        'version' => '1.0.0',
                    ],
                ],
                'packages-dev' => [
                    [
                        'name'    => 'acme/package',
                        'version' => '2.0.0',
                    ],
                ],
            ],
            'root collision' => [
                'packages' => [
                    [
                        'name'    => 'vendor/application',
                        'version' => '1.0.0',
                    ],
                ],
                'packages-dev' => [],
            ],
            'non-object package requirements' => [
                'packages' => [
                    [
                    'name'    => 'acme/package',
                    'version' => '1.0.0',
                    'require' => [
                        'not-a-map-item',
                    ],
                ],
                ],
                'packages-dev' => [],
            ],
            'non-object distribution metadata' => [
                'packages' => [
                    [
                    'name'    => 'acme/package',
                    'version' => '1.0.0',
                    'dist'    => 'invalid',
                ],
                ],
                'packages-dev' => [],
            ],
            'invalid distribution reference' => [
                'packages' => [
                    [
                    'name'    => 'acme/package',
                    'version' => '1.0.0',
                    'dist'    => [
                        'type'      => 'zip',
                        'url'       => 'https://example.test/package.zip',
                        'reference' => false,
                    ],
                ],
                ],
                'packages-dev' => [],
            ],
        ];

        foreach ($invalidLocks as $lock) {
            $this->withTemporaryDirectory(function (
                string $root,
            ) use ($lock): void {
                $this->writeJson($root . '/composer.json', $this->validManifest());

                if (is_string($lock)) {
                    $this->writeFile($root . '/composer.lock', $lock);
                } else {
                    $this->writeJson(
                        $root . '/composer.lock',
                        [
                            'content-hash' => '0123456789abcdef0123456789abcdef',
                            ...$lock,
                        ],
                    );
                }

                $this->assertThrows(
                    ComposerFileException::class,
                    fn() => $this->readPackages($root),
                );
            });
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testResolvesVendorDirectoriesAndPermitsAnExplicitCustomLocator(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $manifest = [
                'name'   => 'vendor/application',
                'config' => [
                    'vendor-dir' => 'configured-vendor',
                ],
            ];
            $lock = [
                'packages' => [
                    [
                        'name'    => 'acme/library',
                        'version' => '1.0.0',
                    ],
                    [
                        'name'    => 'acme/custom',
                        'version' => '2.0.0',
                        'type'    => 'custom-installer',
                    ],
                    [
                        'name'    => 'acme/meta',
                        'version' => '3.0.0',
                        'type'    => 'metapackage',
                    ],
                ],
                'packages-dev' => [],
            ];

            foreach ([
                '/configured-vendor/acme/library',
                '/configured-vendor/acme/custom',
                '/explicit-vendor/acme/library',
                '/explicit-vendor/acme/custom',
                '/packages/custom',
            ] as $directory) {
                $this->makeDirectory($root . $directory);
            }

            $this->writeProject($root, $manifest, $lock);

            $configured = $this->readPackages($root);
            $this->assertSame('library', $configured->root()->composerType());
            $this->assertSame(
                realpath($root . '/configured-vendor/acme/library'),
                $configured->get('acme/library')?->locatedPath(),
            );
            $this->assertSame(
                realpath($root . '/configured-vendor/acme/custom'),
                $configured->get('acme/custom')?->locatedPath(),
            );

            $explicit = $this->readPackages($root, 'explicit-vendor');
            $this->assertSame(
                realpath($root . '/explicit-vendor/acme/library'),
                $explicit->get('acme/library')?->locatedPath(),
            );

            $locator = new class($root . '/packages/custom') implements PackageLocatorInterface {
                public int $calls = 0;

                public function __construct(private readonly string $customPath)
                {
                }

                public function locate(string $packageName, string $composerType, string $vendorDirectory): ?string
                {
                    ++$this->calls;

                    return 'acme/custom' === $packageName ? $this->customPath : $packageName;
                }
            };
            self::assertTrue(unlink($root . '/configured-vendor/.cache/classes/acme/custom.ser'));
            $custom = $this->readPackages($root, packageLocator: $locator);

            $this->assertSame(
                realpath($root . '/packages/custom'),
                $custom->get('acme/custom')?->locatedPath(),
            );
            $this->assertNull($custom->get('acme/meta')?->locatedPath());
            $this->assertSame(2, $locator->calls, 'The metapackage must not be passed to the locator.');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validManifest(): array
    {
        return [
            'name'    => 'vendor/application',
            'type'    => 'project',
            'require' => [
                'php'       => '^8.4',
                'ext-json'  => '*',
                'acme/prod' => '^1.0',
            ],
            'require-dev' => [
                'acme/dev'             => '^2.0',
                'composer-runtime-api' => '^2.0',
                'ext-swoole'           => '^6.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/',
                ],
                'files' => [
                    'functions.php',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    'Tests\\' => 'tests/',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validLock(): array
    {
        return [
            'packages' => [
                [
                    'name'    => 'acme/prod',
                    'version' => '1.2.3',
                    'source'  => [
                        'type'      => 'git',
                        'url'       => 'https://example.test/package.git',
                        'reference' => 'prod-source-reference',
                    ],
                    'dist' => [
                        'type'      => 'zip',
                        'url'       => 'https://example.test/package.zip',
                        'reference' => 'prod-dist-reference',
                    ],
                    'require' => [
                        'acme/shared' => '^3.0',
                        'ext-curl'    => '^8.0',
                        'ext-json'    => '*',
                        'lib-curl'    => '^8.0',
                        'php'         => '^8.1',
                    ],
                    'require-dev' => [
                        'ext-ignored' => '*',
                    ],
                    'autoload' => [
                        'psr-4' => [
                            'Acme\\Prod\\' => 'src/',
                        ],
                    ],
                    'autoload-dev' => [
                        'psr-4' => [
                            'Ignored\\' => '/absolute/path',
                        ],
                    ],
                    'provide' => [
                        'psr/example-implementation' => '1.0',
                    ],
                ],
            ],
            'packages-dev' => [
                [
                    'name'    => 'acme/dev',
                    'version' => 'dev-main',
                    'source'  => [
                        'type'      => 'git',
                        'url'       => 'https://example.test/package.git',
                        'reference' => 'dev-source-reference',
                    ],
                    'type'    => 'custom-installer',
                    'require' => [
                        'acme/dev-helper' => '^1.0',
                        'ext-redis'       => '*',
                    ],
                    'autoload' => [
                        'psr-0' => [
                            'Acme_Dev_' => 'lib/',
                        ],
                    ],
                ],
                [
                    'name'    => 'acme/meta',
                    'version' => '2.0.0',
                    'type'    => 'metapackage',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $lock
     *
     * @throws Throwable When fixture setup or a supplied test callback fails
     */
    private function writeProject(string $root, array $manifest, array $lock): void
    {
        $this->writeJson($root . '/composer.json', $manifest);
        $this->writeJson($root . '/composer.lock', [
            'content-hash' => '0123456789abcdef0123456789abcdef',
            ...$lock,
        ]);
    }

    /**
     * @param list<DependencyInterface|PackageInterface> $items
     *
     * @return list<string>
     */
    private function names(array $items): array
    {
        return array_map(
            static fn(DependencyInterface|PackageInterface $item): string => $item->name(),
            $items,
        );
    }

    /**
     * @param list<DependencyInterface> $dependencies
     */
    private function dependency(array $dependencies, string $name): ?DependencyInterface
    {
        return array_find(
            $dependencies,
            static fn(DependencyInterface $dependency): bool => $dependency->name() === $name,
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
