<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use ReflectionClass;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Composer\Cache\PackageReconciler;
use SuperKernel\Composer\ClassMap\ClassMapBuilder;
use SuperKernel\Composer\Contract\ComposerMetadataInterface;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Contract\PackageReconcilerInterface;
use SuperKernel\Composer\Locator\PackageLocator;
use SuperKernel\Composer\Manager\PackageManager;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Metadata\ComposerPackage;
use SuperKernel\Composer\Metadata\PackageParser;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Path\VendorDir;
use SuperKernel\Composer\Provider\PackageManagerProvider;
use SuperKernel\Composer\Provider\VendorDirProvider;
use SuperKernel\Composer\Tests\Support\CallbackScanProcessor;
use SuperKernel\Composer\Tests\Support\RestoreOnlyScanProcessor;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\PackageManagerInterface;
use SuperKernel\Contract\VendorDirInterface;
use SuperKernel\Tokenizer\ClassNameParser;
use Throwable;

use function class_exists;
use function glob;
use function realpath;
use function spl_autoload_functions;
use function symlink;
use function unlink;

final class PackageManagerProviderTest extends TestCase
{
    public function testProviderRequiresAllThreeServicesAndDeclaresTheSharedManagerContract(): void
    {
        $reflection = new ReflectionClass(PackageManagerProvider::class);
        $parameters = $reflection->getMethod('__invoke')->getParameters();

        self::assertCount(3, $parameters);
        self::assertSame(PackageReconcilerInterface::class, $parameters[0]->getType()->getName());
        self::assertSame(ComposerMetadataInterface::class, $parameters[1]->getType()->getName());

        foreach ($parameters as $parameter) {
            self::assertFalse($parameter->allowsNull());
            self::assertFalse($parameter->isDefaultValueAvailable());
        }

        self::assertSame(VendorDirInterface::class, $parameters[2]->getType()->getName());
        self::assertSame('vendorDir', $parameters[2]->getName());

        self::assertSame(
            PackageManagerInterface::class,
            $reflection->getAttributes(FactoryProvider::class)[0]->getArguments()[0],
        );
    }

    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testProviderConstructsFreshManagersWithoutChangingTheLoader(): void
    {
        $this->withTemporaryDirectory(
            function (
                string $root,
            ): void {
                $this->writeJson($root . '/composer.json', [
                    'name' => 'fixture/application',
                ]);
                $this->writeLock($root, [
                    [
                    'name'     => 'super-kernel/composer',
                    'version'  => 'dev-main',
                    'autoload' => [
                        'psr-4' => [
                            'SuperKernel\\Composer\\' => 'src/',
                        ],
                    ],
                ],
                ]);
                $this->makeDirectory($root . '/vendor/super-kernel');
                self::assertTrue(symlink(realpath(__DIR__ . '/../..'), $root . '/vendor/super-kernel/composer'));
                $loaders = spl_autoload_functions();
                $basePath = new BasePath($root);
                $metadata = new ComposerMetadata($basePath);
                $vendorDir = new VendorDirProvider()($metadata, $basePath);
                $reconciler = new PackageReconciler(
                    new PackageParser($basePath, $vendorDir, new PackageLocator()),
                    new ClassMapBuilder(new ClassNameParser()),
                    $vendorDir,
                    new CallbackScanProcessor(),
                );
                $this->writeInstallationState((string) $vendorDir);
                $provider = new PackageManagerProvider();
                $first = $provider($reconciler, $metadata, $vendorDir);
                $second = $provider($reconciler, $metadata, $vendorDir);

                self::assertInstanceOf(PackageManager::class, $first);
                self::assertInstanceOf(PackageManagerInterface::class, $second);
                self::assertNotSame($first, $second);
                self::assertSame($first->classes('super-kernel/composer'), $second->classes('SUPER-KERNEL/COMPOSER'));
                self::assertSame('super-kernel/composer', $first->packageForClass(PackageManager::class)?->name());
                self::assertNull($first->classes('missing/package'));
                self::assertNull($first->packageForClass('Missing\\PackageClass'));
                self::assertSame($loaders, spl_autoload_functions());
            },
        );
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testInjectedMetadataAndReconcilerDoNotReadComposerFilesAgain(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeJson($root . '/composer.json', [
                'name' => 'fixture/snapshot',
            ]);
            $this->writeLock($root, []);
            $this->writeFile($root . '/vendor/.keep');
            $metadata = new ComposerMetadata(new BasePath($root));
            $vendorDir = new VendorDir($root . '/vendor');
            $reconciler = new PackageReconciler(
                new PackageParser(new BasePath($root), $vendorDir, new PackageLocator()),
                new ClassMapBuilder(new ClassNameParser()),
                $vendorDir,
                new CallbackScanProcessor(),
            );
            $this->writeInstallationState((string) $vendorDir);
            unlink($root . '/composer.json');
            unlink($root . '/composer.lock');

            $manager = new PackageManagerProvider()(
                reconciler: $reconciler,
                metadata: $metadata,
                vendorDir: $vendorDir
            );

            self::assertSame('fixture/snapshot', $manager->all()[0]->name());
            self::assertSame($root, $manager->all()[0]->locatedPath());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testProviderAcceptsAnAlternativeReconcilerThroughTheInterface(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeJson($root . '/composer.json', [
                'name' => 'fixture/application',
            ]);
            $this->writeLock($root, []);
            $parser = new PackageParser(new BasePath($root), new VendorDir($root . '/vendor'), new PackageLocator());
            $package = $parser->parse(new ComposerPackage([
                'name' => 'fixture/application',
            ], root: true), $root);
            $reconciler = $this->createMock(PackageReconcilerInterface::class);
            $reconciler->expects($this->once())->method('reconcile')->with($this->callback(
                static fn(ComposerPackageInterface $metadata): bool => 'fixture/application' === $metadata->name(),
            ))->willReturn($package);
            $reconciler->expects($this->once())->method('classes')->with('fixture/application')->willReturn([]);
            $reconciler->expects($this->once())->method('autoloadFiles')->with('fixture/application')->willReturn([]);

            self::assertSame($package, $this->manager($root, $reconciler)->root());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testInjectedServicesPublishAndRestoreTheSuppliedWorkspace(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->makeDirectory($root . '/dependencies');
            $this->writeJson($root . '/composer.json', [
                'name'   => 'fixture/application',
                'config' => [
                    'vendor-dir' => 'dependencies',
                ],
                'autoload' => [
                    'psr-4' => [
                        'PackageManagerFixture\\' => 'src/',
                    ],
                ],
            ]);
            $this->writeLock($root, []);
            $this->writeFile(
                $root . '/src/Initial.php',
                '<?php namespace PackageManagerFixture; final class Initial {}',
            );
            $metadata = new ComposerMetadata(new BasePath($root));
            $vendorDir = new VendorDirProvider()($metadata, new BasePath($root));

            $parser = new PackageParser(new BasePath($root), $vendorDir, new PackageLocator());
            $builder = new ClassMapBuilder(new ClassNameParser());
            $reconciler = new PackageReconciler($parser, $builder, $vendorDir, new CallbackScanProcessor());
            $this->writeInstallationState((string) $vendorDir);
            $provider = new PackageManagerProvider();
            $first = $provider($reconciler, $metadata, $vendorDir);
            $this->writeFile(
                $root . '/src/Added.php',
                '<?php namespace PackageManagerFixture; final class Added {}',
            );
            $second = $provider($reconciler, $metadata, $vendorDir);
            $restored = $provider(
                new PackageReconciler($parser, $builder, $vendorDir, new RestoreOnlyScanProcessor()),
                $metadata,
                $vendorDir,
            );

            self::assertSame(realpath($root . '/dependencies'), (string) $vendorDir);
            self::assertFileExists($vendorDir . '/.cache/classes/fixture/application.ser');
            self::assertDirectoryDoesNotExist($vendorDir . '/.runtime');
            self::assertSame(
                'fixture/application',
                $second->packageForClass('PackageManagerFixture\\Added')?->name(),
            );
            self::assertNull($first->packageForClass('PackageManagerFixture\\Added'));
            self::assertFalse(class_exists('PackageManagerFixture\\Added', false));
            self::assertSame($second->allClasses(), $restored->allClasses());
            self::assertNotSame($second->root(), $restored->root());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testProviderVisitsCurrentDependenciesAndRootAndOmitsRemovedCachedPackages(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $processor = new CallbackScanProcessor();
            $vendorDir = new VendorDir($root . '/vendor');
            $reconciler = new PackageReconciler(
                new PackageParser(new BasePath($root), $vendorDir, new PackageLocator()),
                new ClassMapBuilder(new ClassNameParser()),
                $vendorDir,
                $processor,
            );
            $first = $this->manager($root, $reconciler);

            self::assertSame(3, $processor->calls);
            self::assertSame(3, $processor->probes);
            self::assertSame([
                $vendorDir . '/.cache/classes/acme/a.ser',
                $vendorDir . '/.cache/classes/acme/b.ser',
                $vendorDir . '/.cache/classes/acme/project.ser',
            ], glob($vendorDir . '/.cache/classes/acme/*.ser'));
            self::assertSame('acme/project', $first->all()[0]->name());
            self::assertCount(2, $first->packages());

            $this->writeLock($root, [
                $this->package('a'),
            ]);
            $second = $this->manager($root, $reconciler);

            self::assertCount(1, $second->packages());
            self::assertNull($second->get('acme/b'));
            self::assertNull($second->classes('acme/b'));
            self::assertSame($first->classes('acme/a'), $second->classes('acme/a'));
            self::assertNotSame($first, $second);
            self::assertSame(5, $processor->calls);
            self::assertFileExists($vendorDir . '/.cache/classes/acme/b.ser');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testProviderOrdersFileAutoloadsByDependenciesAndDeduplicatesCycles(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);
            $this->writeFile($root . '/root.php', '<?php');
            $this->writeFile($root . '/development.php', '<?php');
            $this->writeFile($root . '/vendor/acme/a/functions.php', '<?php');
            $this->writeFile($root . '/vendor/acme/b/functions.php', '<?php');
            $this->writeJson($root . '/composer.json', [
                'name'    => 'acme/project',
                'require' => [
                    'acme/a' => '^1.0',
                ],
                'autoload' => [
                    'files' => [
                        'root.php',
                    ],
                ],
                'autoload-dev' => [
                    'files' => [
                        'root.php',
                        'development.php',
                    ],
                ],
            ]);
            $a = $this->package('a');
            $a['require'] = [
                'acme/b' => '^1.0',
            ];
            $a['autoload']['files'] = [
                'functions.php',
            ];
            $b = $this->package('b');
            $b['require'] = [
                'acme/a' => '^1.0',
            ];
            $b['autoload']['files'] = [
                'functions.php',
            ];
            $this->writeLock($root, [
                $a,
                $b,
            ]);
            $vendorDir = new VendorDir($root . '/vendor');
            $manager = $this->manager($root, new PackageReconciler(
                new PackageParser(new BasePath($root), $vendorDir, new PackageLocator()),
                new ClassMapBuilder(new ClassNameParser()),
                $vendorDir,
                new CallbackScanProcessor(),
            ));

            self::assertSame([
                $root . '/vendor/acme/b/functions.php',
                $root . '/vendor/acme/a/functions.php',
                $root . '/root.php',
                $root . '/development.php',
            ], $manager->autoloadFiles());
        });
    }

    /** @throws Throwable When fixture setup or a supplied test callback fails */
    private function workspace(string $root): void
    {
        $this->writeJson($root . '/composer.json', [
            'name'     => 'acme/project',
            'autoload' => [
                'classmap' => [
                    'src/',
                ],
            ],
        ]);
        $this->writeFile($root . '/src/Root.php', '<?php namespace Application; final class Root {}');
        $this->writeFile($root . '/vendor/acme/a/src/A.php', '<?php namespace Acme\\A; final class Stable {}');
        $this->writeFile($root . '/vendor/acme/b/src/B.php', '<?php namespace Acme\\B; final class Stable {}');
        $this->writeLock($root, [
            $this->package('a'),
            $this->package('b'),
        ]);
    }

    /** @return array<string, mixed> */
    private function package(string $name): array
    {
        return [
            'name'    => 'acme/' . $name,
            'version' => '1.0.0',
            'source'  => [
                'type'      => 'git',
                'url'       => 'https://example.test/package.git',
                'reference' => $name . '-reference',
            ],
            'autoload' => [
                'classmap' => [
                    'src/',
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $packages
     *
     * @throws Throwable When fixture setup or a supplied test callback fails
     */
    private function writeLock(string $root, array $packages): void
    {
        $this->writeJson($root . '/composer.lock', [
            'content-hash' => '0123456789abcdef0123456789abcdef',
            'packages'     => $packages,
            'packages-dev' => [],
        ]);
    }

    /**
     * The native return type narrows the provider's interface result to the PackageManager it constructs, whose
     * autoloadFiles() the assertions inspect.
     *
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    private function manager(string $root, PackageReconcilerInterface $reconciler): PackageManager
    {
        $metadata = new ComposerMetadata(new BasePath($root));
        $vendorDir = new VendorDirProvider()($metadata, new BasePath($root));
        $this->writeInstallationState((string) $vendorDir);

        return new PackageManagerProvider()($reconciler, $metadata, $vendorDir);
    }
}
