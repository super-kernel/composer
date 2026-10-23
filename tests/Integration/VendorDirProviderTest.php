<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use ReflectionClass;
use Stringable;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Composer\Contract\ComposerMetadataInterface;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Path\VendorDir;
use SuperKernel\Composer\Provider\VendorDirProvider;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\BasePathInterface;
use SuperKernel\Contract\VendorDirInterface;
use Throwable;

use function chdir;
use function getcwd;
use function getenv;
use function putenv;
use function realpath;
use function spl_autoload_functions;
use function symlink;

final class VendorDirProviderTest extends TestCase
{
    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testFactoryUsesTheRootConfigurationAndInjectedBasePath(): void
    {
        $attributes = new ReflectionClass(VendorDirProvider::class)->getAttributes(FactoryProvider::class);
        self::assertCount(1, $attributes);
        self::assertSame(VendorDirInterface::class, $attributes[0]->newInstance()->className);
        $parameters = new ReflectionClass(VendorDirProvider::class)->getMethod('__invoke')->getParameters();
        self::assertCount(2, $parameters);
        self::assertSame(ComposerMetadataInterface::class, $parameters[0]->getType()->getName());
        self::assertSame(BasePathInterface::class, $parameters[1]->getType()->getName());

        foreach ($parameters as $parameter) {
            self::assertFalse($parameter->allowsNull());
            self::assertFalse($parameter->isDefaultValueAvailable());
        }

        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $package = $this->createMock(ComposerPackageInterface::class);
            $package->expects(self::once())->method('config')->willReturn([
                'vendor-dir' => 'dependencies',
            ]);
            $metadata = $this->createMock(ComposerMetadataInterface::class);
            $metadata->expects(self::once())->method('root')->willReturn($package);

            $directory = new VendorDirProvider()($metadata, new BasePath($root));

            self::assertInstanceOf(VendorDir::class, $directory);
            self::assertInstanceOf(VendorDirInterface::class, $directory);
            self::assertInstanceOf(Stringable::class, $directory);
            self::assertSame($root . '/dependencies', (string)$directory);
            self::assertDirectoryDoesNotExist($root . '/dependencies');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testResolvesDefaultRelativeAbsoluteAndLinkedDirectoriesFromNativeMetadata(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $project = $root . '/application';
            $this->makeDirectory($project);
            $basePath = new BasePath($project);
            $provider = new VendorDirProvider();
            $this->writeJson($project . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [],
                'packages-dev' => [],
            ]);
            $this->writeJson($project . '/composer.json', [
                'name' => 'fixture/application',
            ]);
            $metadata = new ComposerMetadata($basePath);
            $default = $provider($metadata, $basePath);

            self::assertSame($project . '/vendor', (string)$default);
            self::assertNotSame($default, $provider($metadata, $basePath));
            self::assertDirectoryDoesNotExist((string)$default);

            foreach ([
                'dependencies/./cache/..'  => $project . '/dependencies',
                '../shared'                => $root . '/shared',
                $root . '/external/vendor' => $root . '/external/vendor',
            ] as $configured => $expected) {
                $this->writeJson($project . '/composer.json', [
                    'config' => [
                        'vendor-dir' => $configured,
                    ],
                ]);
                $metadata = new ComposerMetadata($basePath);

                self::assertSame($expected, (string)$provider($metadata, $basePath));
                self::assertDirectoryDoesNotExist($expected);
            }

            $installed = $root . '/installed';
            $this->makeDirectory($installed);
            self::assertTrue(symlink($installed, $project . '/linked'));
            $this->writeJson($project . '/composer.json', [
                'config' => [
                    'vendor-dir' => 'linked',
                ],
            ]);
            $metadata = new ComposerMetadata($basePath);
            $directory = $provider($metadata, $basePath);
            $this->writeJson($project . '/composer.json', [
                'config' => [
                    'vendor-dir' => 'changed',
                ],
            ]);

            self::assertSame(realpath($installed), (string)$directory);
            self::assertSame((string)$directory, (string)$provider($metadata, $basePath));
            self::assertDirectoryDoesNotExist($project . '/changed');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testUsesTheInjectedMetadataSnapshotOnEachInvocation(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $provider = new VendorDirProvider();
            $basePath = new BasePath($root);
            $loaders = spl_autoload_functions();
            $this->writeProject($root, 'dependencies');
            $first = $provider(new ComposerMetadata($basePath), $basePath);
            $this->writeProject($root, 'changed-vendor');
            $second = $provider(new ComposerMetadata($basePath), $basePath);

            self::assertSame($root . '/dependencies', (string)$first);
            self::assertSame($root . '/changed-vendor', (string)$second);
            self::assertNotSame($first, $second);
            self::assertDirectoryDoesNotExist((string)$first);
            self::assertDirectoryDoesNotExist((string)$second);
            self::assertSame($loaders, spl_autoload_functions());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testInjectedMetadataAndBasePathRequireNoComposerFileReads(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $workingDirectory = getcwd();
            $originalMode = getenv('COMPOSER_DEV_MODE');
            self::assertIsString($workingDirectory);
            $this->writeFile($root . '/composer.json', '{');
            $this->writeFile($root . '/composer.lock', '{');
            $package = $this->createMock(ComposerPackageInterface::class);
            $package->expects(self::once())->method('config')->willReturn([
                'vendor-dir' => 'injected-vendor',
            ]);
            $metadata = $this->createMock(ComposerMetadataInterface::class);
            $metadata->expects(self::once())->method('root')->willReturn($package);

            try {
                self::assertTrue(chdir($root));
                self::assertTrue(putenv('COMPOSER_DEV_MODE=0'));
                $directory = new VendorDirProvider()($metadata, new BasePath($root));

                self::assertSame($root . '/injected-vendor', (string)$directory);
                self::assertDirectoryDoesNotExist((string)$directory);
            } finally {
                chdir($workingDirectory);
                putenv(false === $originalMode ? 'COMPOSER_DEV_MODE' : 'COMPOSER_DEV_MODE=' . $originalMode);
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testInjectedDependenciesRemainIndependentOfTheCurrentEnvironment(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $workingDirectory = getcwd();
            $originalMode = getenv('COMPOSER_DEV_MODE');
            self::assertIsString($workingDirectory);
            $provider = new VendorDirProvider();
            $this->writeProject($root . '/first', 'first-vendor');
            $this->writeProject($root . '/second', 'second-vendor');

            try {
                self::assertTrue(putenv('COMPOSER_DEV_MODE=0'));
                self::assertTrue(chdir($root . '/first'));
                $basePath = new BasePath($root . '/first');
                $metadata = new ComposerMetadata($basePath);
                $first = $provider($metadata, $basePath);
                self::assertTrue(chdir($root . '/second'));
                $second = $provider($metadata, $basePath);

                self::assertSame($root . '/first/first-vendor', (string)$first);
                self::assertSame($root . '/first/first-vendor', (string)$second);
                self::assertNotSame($first, $second);
                self::assertDirectoryDoesNotExist((string)$first);
                self::assertDirectoryDoesNotExist((string)$second);
            } finally {
                chdir($workingDirectory);
                putenv(false === $originalMode ? 'COMPOSER_DEV_MODE' : 'COMPOSER_DEV_MODE=' . $originalMode);
            }
        });
    }

    public function testRejectsInvalidConfiguredDirectoriesInsteadOfUsingTheDefault(): void
    {
        $provider = new VendorDirProvider();
        $basePath = new BasePath('/application');

        foreach ([
            null,
            '',
            false,
            123,
            [],
            "invalid\0directory",
        ] as $configured) {
            $package = $this->createStub(ComposerPackageInterface::class);
            $package->method('config')->willReturn([
                'vendor-dir' => $configured,
            ]);
            $metadata = $this->createStub(ComposerMetadataInterface::class);
            $metadata->method('root')->willReturn($package);

            $this->assertThrows(
                ComposerFileException::class,
                fn() => $provider($metadata, $basePath),
                messageContains: 'config.vendor-dir',
            );
        }
    }

    /** @throws Throwable When fixture setup or a supplied test callback fails */
    private function writeProject(string $root, string $vendorDirectory): void
    {
        $this->writeJson($root . '/composer.json', [
            'name'   => 'fixture/application',
            'config' => [
                'vendor-dir' => $vendorDirectory,
            ],
        ]);
        $this->writeJson($root . '/composer.lock', [
            'content-hash' => '0123456789abcdef0123456789abcdef',
            'packages'     => [],
            'packages-dev' => [],
        ]);
    }
}
