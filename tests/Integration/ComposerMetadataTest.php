<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use ReflectionClass;
use ReflectionNamedType;
use SuperKernel\Attribute\Provider;
use SuperKernel\Composer\Contract\ComposerMetadataInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Metadata\ComposerFileReader;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Provider\BasePathProvider;
use SuperKernel\Composer\Provider\VendorDirProvider;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\BasePathInterface;
use Throwable;

use function chdir;
use function getcwd;
use function getenv;
use function putenv;
use function spl_autoload_functions;
use function symlink;

final class ComposerMetadataTest extends TestCase
{
    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testMarkedProviderBuildsFreshMetadataForTheCanonicalWorkspace(): void
    {
        $reflection = new ReflectionClass(ComposerMetadata::class);
        $attributes = $reflection->getAttributes(Provider::class);
        self::assertCount(1, $attributes);
        self::assertSame(ComposerMetadataInterface::class, $attributes[0]->newInstance()->className);

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertSame(1, $constructor->getNumberOfParameters());
        $parameter = $constructor->getParameters()[0];
        self::assertSame('basePath', $parameter->getName());
        self::assertFalse($parameter->allowsNull());
        self::assertFalse($parameter->isDefaultValueAvailable());
        $type = $parameter->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame(BasePathInterface::class, $type->getName());
        $loaders = spl_autoload_functions();
        $root = new BasePathProvider()();
        $first = new ComposerMetadata(basePath: $root);
        $second = new ComposerMetadata($root);

        self::assertInstanceOf(ComposerMetadata::class, $first);
        self::assertInstanceOf(ComposerMetadataInterface::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame(
            ComposerFileReader::readComposerJson($root . '/composer.json')->all(),
            $first->root()->metadata(),
        );
        self::assertEquals($first, $second);
        self::assertSame($loaders, spl_autoload_functions());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testInjectedRootsHaveIndependentSnapshotsRegardlessOfTheCurrentEnvironment(): void
    {
        $defaultRoot = new BasePathProvider()();
        $default = new ComposerMetadata($defaultRoot);
        $loaders = spl_autoload_functions();

        $this->withTemporaryDirectory(function (
            string $root,
        ) use ($defaultRoot, $default, $loaders): void {
            $manifest = [
                'name'     => 'fixture/application',
                'version'  => '1.0.0',
                'autoload' => [
                    'files' => [
                        'functions.php',
                    ],
                ],
            ];
            $this->writeJson($root . '/composer.json', $manifest);
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [],
                'packages-dev' => [],
            ]);
            $this->writeFile(
                $root . '/functions.php',
                '<?php throw new LogicException("Metadata construction must not execute autoload files.");',
            );
            $basePath = new BasePath($root);
            $first = new ComposerMetadata($basePath);
            $manifest['version'] = '2.0.0';
            $this->writeJson($root . '/composer.json', $manifest);
            $second = new ComposerMetadata($basePath);

            self::assertNotSame($first, $second);
            self::assertSame('fixture/application', $first->root()->name());
            self::assertSame('1.0.0', $first->root()->version());
            self::assertSame('2.0.0', $second->root()->version());
            self::assertSame([], $second->all());
            self::assertDirectoryDoesNotExist($root . '/vendor');
            self::assertSame($loaders, spl_autoload_functions());
            self::assertEquals($default, new ComposerMetadata($defaultRoot));
            $workingDirectory = getcwd();
            $originalMode = getenv('COMPOSER_DEV_MODE');
            self::assertIsString($workingDirectory);

            try {
                self::assertTrue(chdir($root));
                self::assertTrue(putenv('COMPOSER_DEV_MODE=0'));
                $provided = new ComposerMetadata($basePath);
                $explicit = new ComposerMetadata($defaultRoot);

                self::assertEquals($second, $provided);
                self::assertNotSame($second, $provided);
                self::assertEquals($default, $explicit);
                self::assertSame($loaders, spl_autoload_functions());
            } finally {
                chdir($workingDirectory);
                putenv(false === $originalMode ? 'COMPOSER_DEV_MODE' : 'COMPOSER_DEV_MODE=' . $originalMode);
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReadsALinkedProjectThroughItsBasePathValueWithoutAnInstallation(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $project = $root . '/application';
            $manifest = [
                'name'   => 'fixture/application',
                'config' => [
                    'vendor-dir' => '../dependencies',
                ],
            ];
            $this->writeJson($project . '/composer.json', $manifest);
            $this->writeJson($project . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [],
                'packages-dev' => [],
            ]);
            self::assertTrue(symlink($project, $root . '/linked-project'));
            $basePath = new BasePath($project);
            $metadata = new ComposerMetadata(new BasePath($root . '/linked-project'));

            self::assertSame($manifest, $metadata->root()->metadata());
            self::assertEquals($metadata, new ComposerMetadata($basePath));
            self::assertDirectoryDoesNotExist($project . '/vendor');
            self::assertDirectoryDoesNotExist($root . '/dependencies');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPreservesVendorConfigurationForValidationByTheVendorDirProvider(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [],
                'packages-dev' => [],
            ]);

            foreach ([
                '',
                "bad\0path",
                '/../../escape',
                null,
                true,
                [],
            ] as $configured) {
                $this->writeJson($root . '/composer.json', [
                    'config' => [
                        'vendor-dir' => $configured,
                    ],
                ]);
                $basePath = new BasePath($root);
                $metadata = new ComposerMetadata($basePath);

                self::assertSame($configured, $metadata->root()->config()['vendor-dir']);
                $this->assertThrows(
                    ComposerFileException::class,
                    fn() => new VendorDirProvider()($metadata, $basePath),
                    messageContains: 'config.vendor-dir',
                );
            }
        });
    }
}
