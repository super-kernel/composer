<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use SuperKernel\Composer\Constants\PackageType;
use SuperKernel\Composer\Contract\ComposerMetadataInterface;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Exception\PackageNotFoundException;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Metadata\ComposerPackage;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Tests\Support\TestCase;
use Throwable;

use function array_keys;
use function array_map;

final class ComposerMetadataTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testUnifiesRootAndLockPackagesWithoutAnInstallation(): void
    {
        $metadata = $this->readMetadata([
            'name' => 'acme/application',
            'type' => 'project',
        ], [
            [
                'name'    => 'acme/plugin',
                'version' => '1.0.0',
                'type'    => 'composer-plugin',
            ],
            [
                'name'    => 'acme/library',
                'version' => '1.0.0',
            ],
            [
                'name'    => 'acme/custom-a',
                'version' => '1.0.0',
                'type'    => 'widget-module',
            ],
            [
                'name'    => 'acme/custom-b',
                'version' => '1.0.0',
                'type'    => 'root',
            ],
            [
                'name'    => 'acme/custom-c',
                'version' => '1.0.0',
                'type'    => '123',
            ],
        ], [
            [
                'name'    => 'acme/dev-extension',
                'version' => '1.0.0',
                'type'    => 'php-ext-zend',
            ],
        ]);

        self::assertTrue($metadata->has('ACME/APPLICATION'));
        self::assertSame($metadata->root(), $metadata->get('ACME/APPLICATION'));
        self::assertSame(PackageType::Root, $metadata->root()->type());
        self::assertSame('project', $metadata->root()->metadata()['type']);
        self::assertFalse($metadata->root()->isDev());
        self::assertSame([
            'acme/custom-a',
            'acme/custom-b',
            'acme/custom-c',
            'acme/dev-extension',
            'acme/library',
            'acme/plugin',
        ], array_map(fn(ComposerPackageInterface $package) => $package->name(), $metadata->all()));
        self::assertSame(PackageType::Library, $metadata->get('acme/library')->type());
        self::assertTrue($metadata->get('acme/dev-extension')->isDev());
        self::assertFalse($metadata->get('acme/plugin')->isDev());
        self::assertSame([
            $metadata->root(),
        ], $metadata->ofType(PackageType::Root));
        self::assertSame([
            $metadata->get('acme/custom-b'),
        ], $metadata->ofType('root'));
        self::assertSame([
            $metadata->get('acme/custom-c'),
        ], $metadata->ofType('123'));
        self::assertSame('widget-module', $metadata->get('acme/custom-a')->type());
        self::assertSame([
            123,
            'Root',
            'composer-plugin',
            'library',
            'php-ext-zend',
            'root',
            'widget-module',
        ], array_keys($metadata->groupedByType()));
        self::assertSame([], $metadata->ofType('absent-type'));
        self::assertFalse($metadata->has('missing/package'));
        $this->assertThrows(PackageNotFoundException::class, fn() => $metadata->get('missing/package'));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPreservesPackageFieldsAndSelectsOnlyDeclaredExtensionNames(): void
    {
        $fields = [
            'name'                => 'acme/package',
            'version'             => 'v1.2.3',
            'version_normalized'  => '1.2.3.0',
            'type'                => 'library',
            'description'         => 'Fixture package',
            'license'             => 'MIT',
            'bin'                 => 'bin/example',
            '_comment'            => 'Fixture comment',
            'notification-url'    => 'https://example.com/downloads',
            'installation-source' => 'dist',
            'transport-options'   => [
                'http' => [
                    'timeout' => 10,
                ],
            ],
            'source' => [
                'type'      => 'git',
                'url'       => 'https://example.com/source.git',
                'reference' => 'source-reference',
            ],
            'dist' => [
                'type'      => 'zip',
                'url'       => 'https://example.com/package.zip',
                'reference' => 'dist-reference',
            ],
            'require' => [
                'php'                  => '^8.4',
                'ext-xml'              => '*',
                'ext-json'             => '*',
                'lib-curl'             => '*',
                'composer-runtime-api' => '^2.0',
            ],
            'require-dev' => [
                'ext-dom'  => '*',
                'EXT-JSON' => '*',
            ],
            'suggest' => [
                'ext-xdebug' => 'Optional debugging',
            ],
            'support' => [
                'issues' => 'https://example.com/issues',
            ],
            'extra' => [
                'custom' => [
                    'enabled' => true,
                ],
            ],
            'vendor-field' => [
                'preserved' => true,
            ],
        ];
        $metadata = $this->readMetadata([
            'name' => 'acme/application',
        ], [
            $fields,
        ]);
        $package = $metadata->get('acme/package');

        self::assertSame($fields, $package->metadata());
        self::assertSame('v1.2.3', $package->version());
        self::assertSame('1.2.3.0', $package->normalizedVersion());
        self::assertSame('dist-reference', $package->reference());
        self::assertSame('Fixture package', $package->description());
        self::assertSame([
            'MIT',
        ], $package->license());
        self::assertSame([
            'bin/example',
        ], $package->bin());
        self::assertSame([
            'Fixture comment',
        ], $package->comment());
        self::assertSame([
            'ext-dom',
            'ext-json',
            'ext-xml',
        ], $package->extensions());
        self::assertSame([
            'ext-json',
            'ext-xml',
        ], $package->extensions(false));
        self::assertSame($fields['require'], $package->require());
        self::assertSame($fields['require-dev'], $package->requireDev());
        self::assertSame($fields['suggest'], $package->suggest());
        self::assertSame($fields['source'], $package->source());
        self::assertSame($fields['dist'], $package->dist());
        self::assertSame($fields['support'], $package->support());
        self::assertSame($fields['extra'], $package->extra());
        self::assertSame($fields['transport-options'], $package->transportOptions());
        self::assertSame($fields['notification-url'], $package->notificationUrl());
        self::assertSame('dist', $package->installationSource());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testNormalizesAllAutoloadDeclarationsWithoutResolvingPaths(): void
    {
        $metadata = $this->readMetadata([
            'name'     => 'acme/application',
            'autoload' => [
                'psr-0' => [
                    'Legacy_' => 'legacy/',
                ],
                'psr-4' => [
                    'Acme\\' => [
                        'src/',
                        'fallback/',
                        'src/',
                    ],
                    '' => '',
                ],
                'classmap' => [
                    'classes/',
                    'one.php',
                    'classes/',
                ],
                'exclude-from-classmap' => [
                    '/Tests/',
                ],
                'files' => [
                    'functions.php',
                    'helpers.php',
                    'functions.php',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    'Acme\\Tests\\' => 'tests/',
                ],
                'files' => [
                    'test-functions.php',
                ],
            ],
        ]);
        $root = $metadata->root();

        self::assertSame([
            'psr-0' => [
                'Legacy_' => [
                    'legacy/',
                ],
            ],
            'psr-4' => [
                'Acme\\' => [
                    'src/',
                    'fallback/',
                ],
                '' => [
                    '',
                ],
            ],
            'classmap' => [
                'classes/',
                'one.php',
            ],
            'exclude-from-classmap' => [
                '/Tests/',
            ],
        ], $root->autoload()->classRules());
        self::assertSame([
            'functions.php',
            'helpers.php',
        ], $root->autoload()->files());
        self::assertSame([
            'Acme\\Tests\\' => [
                'tests/',
            ],
        ], $root->autoloadDev()->classRules()['psr-4']);
        self::assertSame([
            'test-functions.php',
        ], $root->autoloadDev()->files());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testKeepsAnUnnamedRootUniqueAndReturnsNullForAbsentValues(): void
    {
        $metadata = $this->readMetadata([
            'description' => 'Unnamed root',
        ]);

        self::assertNull($metadata->root()->name());
        self::assertNull($metadata->root()->version());
        self::assertNull($metadata->root()->reference());
        self::assertNull($metadata->root()->source());
        self::assertNull($metadata->root()->dist());
        self::assertFalse($metadata->has('__root__'));
        self::assertSame([], $metadata->all());
        self::assertSame([], $metadata->root()->extensions());
        self::assertSame([], $metadata->root()->license());
        self::assertSame([], $metadata->root()->autoload()->files());
        self::assertSame([
            'psr-0'                 => [],
            'psr-4'                 => [],
            'classmap'              => [],
            'exclude-from-classmap' => [],
        ], $metadata->root()->autoload()->classRules());
        self::assertSame([
            'Root' => [
                $metadata->root(),
            ],
        ], $metadata->groupedByType());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReferenceFallsBackToSourceWithoutUsingTheVersion(): void
    {
        $metadata = $this->readMetadata([
            'name'    => 'acme/application',
            'version' => '1.0.0',
        ], [
            [
                'name'    => 'acme/source',
                'version' => '2.0.0',
                'source'  => [
                    'type'      => 'git',
                    'url'       => 'https://example.com/source.git',
                    'reference' => 'source-only',
                ],
            ],
        ]);

        self::assertNull($metadata->root()->reference());
        self::assertSame('source-only', $metadata->get('acme/source')->reference());
    }

    public function testRejectsDuplicateNamesAcrossRootProductionAndDevelopment(): void
    {
        $package = [
            'name'    => 'acme/package',
            'version' => '1.0.0',
        ];

        $this->assertThrows(ComposerFileException::class, fn() => $this->readMetadata([
            'name' => 'acme/package',
        ], [
            $package,
        ]));
        $this->assertThrows(ComposerFileException::class, fn() => $this->readMetadata([
            'name' => 'acme/application',
        ], [
            $package,
        ], [
            $package,
        ]));
    }

    public function testLockedPackagesCannotMasqueradeAsRoot(): void
    {
        $this->assertThrows(ComposerFileException::class, fn() => new ComposerPackage([
            'name' => 'acme/package',
            'type' => 'Root',
        ]));
        $this->assertThrows(ComposerFileException::class, fn() => new ComposerPackage([]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsInvalidTypedLockFieldsAndUnavailableInputs(): void
    {
        foreach ([
            'version_normalized'  => 12,
            'notification-url'    => [],
            'installation-source' => true,
            'transport-options'   => 'invalid',
        ] as $field => $invalid) {
            $this->assertThrows(ComposerFileException::class, fn() => $this->readMetadata([
                'name' => 'acme/application',
            ], [
                [
                    'name'    => 'acme/package',
                    'version' => '1.0.0',
                    $field    => $invalid,
                ],
            ]));
        }

        $this->assertThrows(ComposerFileException::class, fn() => new ComposerMetadata(new BasePath('')));
        $this->assertThrows(ComposerFileException::class, fn() => new ComposerMetadata(new BasePath("bad\0path")));
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeJson($root . '/composer.json', [
                'name' => 'acme/application',
            ]);
            $this->assertThrows(ComposerFileException::class, fn() => new ComposerMetadata(new BasePath($root)));
        });
    }

    /**
     * @param array<string, mixed>       $manifest
     * @param list<array<string, mixed>> $packages
     * @param list<array<string, mixed>> $development
     *
     * @throws Throwable When fixture setup or a supplied test callback fails
     */
    private function readMetadata(
        array $manifest,
        array $packages = [],
        array $development = [],
    ): ComposerMetadataInterface
    {
        return $this->withTemporaryDirectory(function (
            string $root,
        ) use (
            $manifest,
            $packages,
            $development,
        ): ComposerMetadataInterface {
            $this->writeJson($root . '/composer.json', $manifest);
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => $packages,
                'packages-dev' => $development,
            ]);
            $metadata = new ComposerMetadata(new BasePath($root));
            self::assertDirectoryDoesNotExist($root . '/vendor');

            return $metadata;
        });
    }
}
