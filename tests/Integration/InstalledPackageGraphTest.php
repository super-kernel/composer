<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use SuperKernel\Composer\Cache\PackageReconciler;
use SuperKernel\Composer\ClassMap\ClassMapBuilder;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\Composer\Locator\PackageLocator;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Metadata\PackageParser;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Path\VendorDir;
use SuperKernel\Composer\Provider\PackageManagerProvider;
use SuperKernel\Composer\Tests\Support\CallbackScanProcessor;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\PackageManagerInterface;
use SuperKernel\Tokenizer\ClassNameParser;
use Throwable;

use function var_export;

final class InstalledPackageGraphTest extends TestCase
{
    /** @throws Throwable When fixture setup or manager construction fails */
    public function testProductionInstallationSkipsAbsentDevelopmentPackagesAndKeepsRootDevelopmentRules(): void
    {
        $this->withTemporaryDirectory(function (string $root): void {
            $this->fixture($root, false, false, false);
            $metadata = new ComposerMetadata(new BasePath($root));
            self::assertCount(2, $metadata->all());
            self::assertTrue($metadata->get('fixture/development')->isDev());

            $manager = $this->manager($root);

            self::assertCount(2, $manager->all());
            self::assertNull($manager->get('fixture/development'));
            self::assertNull($manager->classes('fixture/development'));
            self::assertSame('fixture/production', $manager->packageForClass('Production\\Example')?->name());
            self::assertSame('fixture/project', $manager->packageForClass('Application\\Example')?->name());
            self::assertSame('fixture/project', $manager->packageForClass('ApplicationTests\\Example')?->name());
            self::assertFileDoesNotExist($root . '/dependencies/.cache/classes/fixture/development.ser');
        });
    }

    /** @throws Throwable When fixture setup or manager construction fails */
    public function testDevelopmentInstallationDiscoversInstalledDevelopmentPackages(): void
    {
        $this->withTemporaryDirectory(function (string $root): void {
            $this->fixture($root, true, true);
            $manager = $this->manager($root);

            self::assertCount(3, $manager->all());
            self::assertSame('fixture/development', $manager->packageForClass('Development\\Example')?->name());
            self::assertSame('fixture/project', $manager->packageForClass('ApplicationTests\\Example')?->name());
        });
    }

    /** @throws Throwable When fixture setup or manager construction fails */
    public function testExplicitlyInstalledDevelopmentPackageIsRetainedEvenInProductionMode(): void
    {
        $this->withTemporaryDirectory(function (string $root): void {
            $this->fixture($root, false, true);

            self::assertSame(
                'fixture/development',
                $this->manager($root)->packageForClass('Development\\Example')?->name(),
            );
        });
    }

    /** @throws Throwable When fixture setup or manager construction fails */
    public function testMissingProductionDirectoryRemainsFatal(): void
    {
        $this->withTemporaryDirectory(function (string $root): void {
            $this->fixture($root, false, false, false, false);

            $this->assertThrows(PackageLocationException::class, fn() => $this->manager($root), 'fixture/production');
        });
    }

    /** @throws Throwable When fixture setup or manager construction fails */
    public function testRecordedDevelopmentPackageWithMissingDirectoryRemainsFatal(): void
    {
        $this->withTemporaryDirectory(function (string $root): void {
            $this->fixture($root, false, true, false);

            $this->assertThrows(PackageLocationException::class, fn() => $this->manager($root), 'fixture/development');
        });
    }

    /** @throws Throwable When fixture setup or manager construction fails */
    public function testDevelopmentModeDoesNotHideAMissingDevelopmentPackage(): void
    {
        $this->withTemporaryDirectory(function (string $root): void {
            $this->fixture($root, true, false, false);

            $this->assertThrows(PackageLocationException::class, fn() => $this->manager($root), 'fixture/development');
        });
    }

    /** @throws Throwable When fixture setup or manager construction fails */
    public function testInvalidInstallationStateIsFatalInsteadOfGuessingFromDirectories(): void
    {
        $this->withTemporaryDirectory(function (string $root): void {
            $this->fixture($root, false, false, false);
            $this->writeFile($root . '/dependencies/composer/installed.php', '<?php return [];');

            $this->assertThrows(
                ComposerFileException::class,
                fn() => $this->manager($root),
                'Invalid Composer installation state',
            );
        });
    }

    /** @throws Throwable When fixture files cannot be created */
    private function fixture(
        string $root,
        bool   $developmentMode,
        bool   $recordDevelopment,
        bool   $developmentDirectory = true,
        bool   $productionDirectory = true,
    ): void
    {
        $this->writeJson($root . '/composer.json', [
            'name'   => 'fixture/project',
            'config' => [
                'vendor-dir' => 'dependencies',
            ],
            'autoload' => [
                'classmap' => [
                    'src/',
                ],
            ],
            'autoload-dev' => [
                'classmap' => [
                    'tests/',
                ],
            ],
        ]);
        $this->writeJson($root . '/composer.lock', [
            'content-hash' => '0123456789abcdef0123456789abcdef',
            'packages'     => [
                [
                'name'     => 'fixture/production',
                'version'  => '1.0.0',
                'autoload' => [
                    'classmap' => [
                        'src/',
                    ],
                ],
            ],
            ],
            'packages-dev' => [
                [
                'name'     => 'fixture/development',
                'version'  => '1.0.0',
                'autoload' => [
                    'classmap' => [
                        'src/',
                    ],
                ],
            ],
            ],
        ]);
        $this->writeFile($root . '/src/Example.php', '<?php namespace Application; final class Example {}');
        $this->writeFile($root . '/tests/Example.php', '<?php namespace ApplicationTests; final class Example {}');

        if ($productionDirectory) {
            $this->writeFile(
                $root . '/dependencies/fixture/production/src/Example.php',
                '<?php namespace Production; final class Example {}',
            );
        }

        if ($developmentDirectory) {
            $this->writeFile(
                $root . '/dependencies/fixture/development/src/Example.php',
                '<?php namespace Development; final class Example {}',
            );
        }

        $versions = [
            'fixture/production' => [
                'dev_requirement' => false,
            ],
        ];

        if ($recordDevelopment) {
            $versions['fixture/development'] = [
                'dev_requirement' => true,
            ];
        }

        $this->writeFile($root . '/dependencies/composer/installed.php', '<?php return ' . var_export([
            'root' => [
                'dev' => $developmentMode,
            ],
            'versions' => $versions,
        ], true) . ';');
    }

    private function manager(string $root): PackageManagerInterface
    {
        $basePath = new BasePath($root);
        $vendorDir = new VendorDir($root . '/dependencies');
        $reconciler = new PackageReconciler(
            new PackageParser($basePath, $vendorDir, new PackageLocator()),
            new ClassMapBuilder(new ClassNameParser()),
            $vendorDir,
            new CallbackScanProcessor(),
        );

        return new PackageManagerProvider()($reconciler, new ComposerMetadata($basePath), $vendorDir);
    }
}
