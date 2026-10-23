<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use RuntimeException;
use SuperKernel\Composer\Contract\PackageLocatorInterface;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\Composer\Locator\PackageLocator;
use SuperKernel\Composer\Metadata\ComposerPackage;
use SuperKernel\Composer\Metadata\PackageParser;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Path\VendorDir;
use SuperKernel\Composer\Tests\Support\TestCase;
use Throwable;

use function realpath;
use function symlink;

final class PackageParserLocationTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testLocatesExistingPackagesInTheStandardVendorLayout(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $vendor = $root . '/configured-vendor';
            $this->makeDirectory($vendor . '/acme/library');
            $parser = new PackageParser(new BasePath($root), new VendorDir($vendor), new PackageLocator());

            foreach ([
                'library',
                'project',
                'composer-plugin',
                'custom-installer',
            ] as $type) {
                $package = new ComposerPackage([
                    'name'    => 'acme/library',
                    'version' => '1.0.0',
                    'type'    => $type,
                ]);

                self::assertSame(realpath($vendor . '/acme/library'), $parser->locate($package));
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsMissingProductionAndDevelopmentPackageDirectories(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $vendor = $root . '/vendor';
            $this->makeDirectory($vendor);
            $parser = new PackageParser(new BasePath($root), new VendorDir($vendor), new PackageLocator());

            foreach ([
                false,
                true,
            ] as $development) {
                $package = new ComposerPackage([
                    'name'    => 'acme/missing',
                    'version' => '1.0.0',
                ], $development);
                $exception = $this->assertThrows(
                    PackageLocationException::class,
                    fn() => $parser->locate($package),
                    'acme/missing',
                );

                self::assertStringContainsString($vendor . '/acme/missing', $exception->getMessage());
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCanonicalizesTheStandardEntryForAPathRepository(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $vendor = $root . '/vendor';
            $source = $root . '/.vendor/component';
            $this->makeDirectory($vendor . '/super-kernel');
            $this->makeDirectory($source);
            self::assertTrue(symlink($source, $vendor . '/super-kernel/component'));
            $parser = new PackageParser(new BasePath($root), new VendorDir($vendor), new PackageLocator());
            $package = new ComposerPackage([
                'name'    => 'super-kernel/component',
                'version' => '1.0.0',
            ]);

            self::assertSame(realpath($source), $parser->locate($package));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRootAndMetapackagesDoNotInvokeTheCustomLocator(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $locator = $this->createMock(PackageLocatorInterface::class);
            $locator->expects($this->never())->method('locate');
            $parser = new PackageParser(new BasePath($root), new VendorDir($root . '/missing-vendor'), $locator);
            $fields = [
                'name' => 'acme/meta',
                'type' => 'metapackage',
            ];

            self::assertSame($root, $parser->locate(new ComposerPackage($fields, root: true)));
            self::assertNull($parser->locate(new ComposerPackage($fields)));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testResolvesAbsoluteAndVendorRelativeCustomLocations(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $vendor = $root . '/vendor';
            $source = $root . '/packages/custom';
            $this->makeDirectory($vendor);
            $this->makeDirectory($source);
            $package = new ComposerPackage([
                'name'    => 'acme/custom',
                'version' => '1.0.0',
                'type'    => 'custom-installer',
            ]);

            foreach ([
                $source,
                '../packages/custom',
            ] as $candidate) {
                $locator = $this->createMock(PackageLocatorInterface::class);
                $locator->expects($this->once())->method('locate')
                    ->with('acme/custom', 'custom-installer', $vendor)->willReturn($candidate);
                $parser = new PackageParser(new BasePath($root), new VendorDir($vendor), $locator);

                self::assertSame(realpath($source), $parser->locate($package));
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsInvalidCustomCandidatesWithoutFallingBackToAnExistingStandardEntry(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $vendor = $root . '/vendor';
            $this->makeDirectory($vendor . '/acme/package');
            $this->writeFile($root . '/candidate-file');
            $package = new ComposerPackage([
                'name'    => 'acme/package',
                'version' => '1.0.0',
            ]);

            foreach ([
                null,
                '',
                "invalid\0path",
                '../missing',
                $root . '/candidate-file',
            ] as $candidate) {
                $locator = $this->createMock(PackageLocatorInterface::class);
                $locator->expects($this->once())->method('locate')->willReturn($candidate);
                $parser = new PackageParser(new BasePath($root), new VendorDir($vendor), $locator);

                $this->assertThrows(
                    PackageLocationException::class,
                    fn() => $parser->locate($package),
                    'acme/package',
                );
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPropagatesFailuresFromTheCustomLocator(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $failure = new RuntimeException('Custom installer unavailable.');
            $locator = $this->createMock(PackageLocatorInterface::class);
            $locator->expects($this->once())->method('locate')->willThrowException($failure);
            $parser = new PackageParser(new BasePath($root), new VendorDir($root . '/vendor'), $locator);
            $package = new ComposerPackage([
                'name'    => 'acme/package',
                'version' => '1.0.0',
            ]);

            self::assertSame($failure, $this->assertThrows(RuntimeException::class, fn() => $parser->locate($package)));
        });
    }
}
