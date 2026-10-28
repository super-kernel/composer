<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use ReflectionClass;
use SuperKernel\Attribute\Provider;
use SuperKernel\Composer\Cache\PackageReconciler;
use SuperKernel\Composer\ClassMap\ClassMapBuilder;
use SuperKernel\Composer\Contract\PackageLocatorInterface;
use SuperKernel\Composer\Contract\PackageParserInterface;
use SuperKernel\Composer\Contract\PackageReconcilerInterface;
use SuperKernel\Composer\Locator\PackageLocator;
use SuperKernel\Composer\Metadata\ComposerPackage;
use SuperKernel\Composer\Metadata\PackageParser;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Path\VendorDir;
use SuperKernel\Composer\Tests\Support\CallbackScanProcessor;
use SuperKernel\Composer\Tests\Support\RestoreOnlyScanProcessor;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\VendorDirInterface;
use SuperKernel\ScanIsolation\Contract\ScanProcessorInterface;
use SuperKernel\ScanIsolation\Exception\ScanProcessorUnavailableException;
use SuperKernel\ScanIsolation\Processor\NullScanProcessor;
use SuperKernel\Tokenizer\ClassNameParser;
use Throwable;

final class PackageReconcilerInjectionTest extends TestCase
{
    public function testProviderDeclaresTheInterfaceAndRequiresItsServices(): void
    {
        $reflection = new ReflectionClass(PackageReconciler::class);
        $attributes = $reflection->getAttributes(Provider::class);
        self::assertCount(1, $attributes);
        self::assertSame(PackageReconcilerInterface::class, $attributes[0]->newInstance()->className);
        $method = $reflection->getConstructor();
        self::assertNotNull($method);
        $expected = [
            'parser'        => PackageParserInterface::class,
            'builder'       => ClassMapBuilder::class,
            'vendorDir'     => VendorDirInterface::class,
            'scanProcessor' => ScanProcessorInterface::class,
        ];
        self::assertCount(4, $method->getParameters());

        foreach ($method->getParameters() as $parameter) {
            self::assertSame($expected[$parameter->getName()], $parameter->getType()->getName());
            self::assertFalse($parameter->allowsNull());
            self::assertFalse($parameter->isDefaultValueAvailable());
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testUsesTheInjectedParserWithoutReadingComposerFiles(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $vendorDir = new VendorDir($root . '/vendor');
            $metadata = new ComposerPackage([
                'name' => 'fixture/application',
            ], root: true);
            $package = new PackageParser(
                new BasePath($root),
                $vendorDir,
                new PackageLocator(),
            )->parse($metadata, $root);
            $this->writeFile($root . '/functions.php', '<?php');
            $parser = $this->createMock(PackageParserInterface::class);
            $parser->expects($this->once())->method('locate')->with($metadata)->willReturn($root);
            $parser->expects($this->once())->method('parse')->with($metadata, $root)->willReturn($package);
            $parser->expects($this->once())->method('autoloadFiles')->with($metadata, $root)->willReturn([
                $root . '/functions.php',
            ]);

            $reconciler = new PackageReconciler(
                $parser,
                new ClassMapBuilder(new ClassNameParser()),
                $vendorDir,
                new CallbackScanProcessor(),
            );
            $restored = $reconciler->reconcile($metadata);

            self::assertNotSame($package, $restored);
            self::assertSame($package->name(), $restored->name());
            self::assertSame($root, $restored->locatedPath());
            self::assertSame([
                $root . '/functions.php',
            ], $reconciler->autoloadFiles('fixture/application'));
            self::assertFileExists($vendorDir . '/.cache/classes/' . $metadata->name() . '.ser');
        });
    }

    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testUsesTheInjectedProcessorAndCustomLocatorForTheSuppliedWorkspace(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $basePath = new BasePath($root);
            $vendorDir = new VendorDir($root . '/dependencies');
            $this->makeDirectory((string) $vendorDir);
            $source = $root . '/packages/custom';
            $this->writeFile($source . '/src/Feature.source', '<?php namespace Acme; final class Feature {}');

            $processor = new CallbackScanProcessor();
            $locator = $this->createMock(PackageLocatorInterface::class);
            $locator->expects($this->once())->method('locate')
                ->with('acme/custom', 'custom-installer', (string) $vendorDir)->willReturn('../packages/custom');
            $parser = new PackageParser($basePath, $vendorDir, $locator);
            $reconciler = new PackageReconciler(
                $parser,
                new ClassMapBuilder(new ClassNameParser()),
                $vendorDir,
                $processor,
            );
            $metadata = new ComposerPackage([
                'name'     => 'acme/custom',
                'version'  => '1.0.0',
                'type'     => 'custom-installer',
                'autoload' => [
                    'classmap' => [
                        'src/',
                    ],
                ],
            ]);
            self::assertInstanceOf(PackageReconciler::class, $reconciler);
            self::assertSame(0, $processor->probes);
            $package = $reconciler->reconcile($metadata);

            self::assertSame($source, $package->locatedPath());
            self::assertSame(1, $processor->probes);
            self::assertSame(1, $processor->calls);
            self::assertFileExists($vendorDir . '/.cache/classes/' . $metadata->name() . '.ser');
            self::assertSame([
                'Acme\\Feature' => $source . '/src/Feature.source',
            ], $reconciler->classes('acme/custom'));
            self::assertSame([], $reconciler->autoloadFiles('acme/custom'));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDoesNotReplaceAnInjectedUnsupportedProcessor(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {

            $processor = new CallbackScanProcessor();
            $processor->supported = false;
            $vendorDir = new VendorDir($root . '/vendor');
            $reconciler = new PackageReconciler(
                new PackageParser(new BasePath($root), $vendorDir, new PackageLocator()),
                new ClassMapBuilder(new ClassNameParser()),
                $vendorDir,
                $processor,
            );
            self::assertSame(0, $processor->probes);
            $metadata = new ComposerPackage([
                'name' => 'fixture/application',
            ], root: true);

            $this->assertThrows(ScanProcessorUnavailableException::class, fn() => $reconciler->reconcile($metadata));
            self::assertSame(1, $processor->probes);
            self::assertSame(0, $processor->calls);
            self::assertDirectoryDoesNotExist($vendorDir . '/.cache');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsNullProcessorOutsidePharBeforeCacheAccess(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {

            $vendorDir = new VendorDir($root . '/vendor');
            $reconciler = new PackageReconciler(
                new PackageParser(new BasePath($root), $vendorDir, new PackageLocator()),
                new ClassMapBuilder(new ClassNameParser()),
                $vendorDir,
                new NullScanProcessor(),
            );
            $metadata = new ComposerPackage([
                'name' => 'fixture/application',
            ], root: true);

            $this->assertThrows(ScanProcessorUnavailableException::class, fn() => $reconciler->reconcile($metadata));
            self::assertDirectoryDoesNotExist($vendorDir . '/.cache');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testFreshReconcilersShareTheInjectedVendorDirectoryWithoutReadingComposerFiles(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $vendorDir = new VendorDir($root . '/dependencies');
            $this->writeFile($root . '/src/Example.source', '<?php namespace Fixture; final class Example {}');
            $metadata = new ComposerPackage([
                'name'     => 'fixture/application',
                'autoload' => [
                    'classmap' => [
                        'src/',
                    ],
                ],
            ], root: true);

            $parser = new PackageParser(new BasePath($root), $vendorDir, new PackageLocator());
            $builder = new ClassMapBuilder(new ClassNameParser());
            $processor = new CallbackScanProcessor();
            $first = new PackageReconciler($parser, $builder, $vendorDir, $processor);
            $second = new PackageReconciler($parser, $builder, $vendorDir, $processor);
            self::assertNotSame($first, $second);

            foreach ([
                $first,
                $second,
            ] as $reconciler) {
                self::assertSame($root, $reconciler->reconcile($metadata)->locatedPath());
                self::assertSame([
                    'Fixture\\Example' => $root . '/src/Example.source',
                ], $reconciler->classes('fixture/application'));
            }

            self::assertFileExists($vendorDir . '/.cache/classes/fixture/application.ser');
            self::assertSame(2, $processor->calls);
            self::assertFileDoesNotExist($root . '/composer.json');
            self::assertFileDoesNotExist($root . '/composer.lock');
            self::assertDirectoryDoesNotExist($root . '/vendor');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCreatesTheCacheInAnUninstalledCustomVendorDirectory(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeJson($root . '/composer.json', [
                'name'   => 'fixture/application',
                'config' => [
                    'vendor-dir' => 'missing-vendor',
                ],
            ]);
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [],
                'packages-dev' => [],
            ]);
            $source = $root . '/packages/custom';
            $this->makeDirectory($source);
            $locator = $this->createMock(PackageLocatorInterface::class);
            $locator->expects($this->once())->method('locate')
                ->with('acme/custom', 'custom-installer', $root . '/missing-vendor')->willReturn($source);

            $vendorDir = new VendorDir($root . '/missing-vendor');
            $reconciler = new PackageReconciler(
                new PackageParser(new BasePath($root), $vendorDir, $locator),
                new ClassMapBuilder(new ClassNameParser()),
                $vendorDir,
                new CallbackScanProcessor(),
            );
            $metadata = new ComposerPackage([
                'name'    => 'acme/custom',
                'version' => '1.0.0',
                'type'    => 'custom-installer',
            ]);

            self::assertSame($source, $reconciler->reconcile($metadata)->locatedPath());
            self::assertFileExists($vendorDir . '/.cache/classes/' . $metadata->name() . '.ser');
            self::assertDirectoryDoesNotExist($root . '/vendor');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPreservesAnExplicitRestoreOnlyProcessor(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $basePath = new BasePath($root);
            $vendorDir = new VendorDir($root . '/vendor');

            $metadata = new ComposerPackage([
                'name' => 'fixture/application',
            ], root: true);
            $parser = new PackageParser($basePath, $vendorDir, new PackageLocator());
            $builder = new ClassMapBuilder(new ClassNameParser());
            $prepared = new PackageReconciler(
                $parser,
                $builder,
                $vendorDir,
                new CallbackScanProcessor(),
            )->reconcile($metadata);
            $restored = new PackageReconciler(
                $parser,
                $builder,
                $vendorDir,
                new RestoreOnlyScanProcessor(),
            )->reconcile($metadata);

            self::assertNotSame($prepared, $restored);
            self::assertSame($prepared->name(), $restored->name());
            self::assertFileExists($vendorDir . '/.cache/classes/' . $metadata->name() . '.ser');
        });
    }
}
