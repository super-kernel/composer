<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use ReflectionClass;
use ReflectionNamedType;
use SuperKernel\Attribute\Provider;
use SuperKernel\Composer\Contract\PackageLocatorInterface;
use SuperKernel\Composer\Contract\PackageParserInterface;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\Composer\Locator\PackageLocator;
use SuperKernel\Composer\Metadata\ComposerPackage;
use SuperKernel\Composer\Metadata\PackageParser;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Path\VendorDir;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\BasePathInterface;
use SuperKernel\Contract\VendorDirInterface;
use Throwable;

final class PackageParserInjectionTest extends TestCase
{
    public function testDeclaresTheParserInterfaceAndRequiresWorkspaceServices(): void
    {
        $reflection = new ReflectionClass(PackageParser::class);
        $attributes = $reflection->getAttributes(Provider::class);
        self::assertCount(1, $attributes);
        self::assertSame(PackageParserInterface::class, $attributes[0]->newInstance()->className);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertSame(3, $constructor->getNumberOfParameters());
        $parameters = $constructor->getParameters();

        foreach ([
            [
                'basePath',
                BasePathInterface::class,
            ],
            [
                'vendorDir',
                VendorDirInterface::class,
            ],
            [
                'locator',
                PackageLocatorInterface::class,
            ],
        ] as $index => [$name, $class]) {
            $parameter = $parameters[$index];
            self::assertSame($name, $parameter->getName());
            $type = $parameter->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type);
            self::assertSame($class, $type->getName());
            self::assertFalse($parameter->allowsNull());
            self::assertFalse($parameter->isDefaultValueAvailable());
        }
    }

    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testBuildsFreshParsersForTheSuppliedWorkspaceAndLocator(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $basePath = new BasePath($root);
            $vendorDir = new VendorDir($root . '/dependencies');
            $directory = $vendorDir . '/acme/library';
            $this->writeFile(
                $directory . '/functions.php',
                '<?php throw new \\RuntimeException("Do not execute.");',
            );
            $metadata = new ComposerPackage([
                'name'     => 'acme/library',
                'version'  => '1.0.0',
                'autoload' => [
                    'files' => [
                        'functions.php',
                    ],
                ],
            ]);
            $rootMetadata = new ComposerPackage([
                'name' => 'fixture/application',
            ], root: true);
            $locator = new PackageLocator();
            $first = new PackageParser(basePath: $basePath, vendorDir: $vendorDir, locator: $locator);
            $second = new PackageParser($basePath, $vendorDir, $locator);
            self::assertInstanceOf(PackageParser::class, $first);
            self::assertNotSame($first, $second);

            foreach ([
                $first,
                $second,
            ] as $parser) {
                self::assertSame($root, $parser->locate($rootMetadata));
                $location = $parser->locate($metadata);
                self::assertSame($directory, $location);
                $package = $parser->parse($metadata, $location);
                self::assertSame('acme/library', $package->name());
                self::assertSame($directory, $package->locatedPath());
                self::assertSame([
                    $directory . '/functions.php',
                ], $parser->autoloadFiles($metadata, $location));
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPreservesACustomLocatorsFailureWithAnExistingStandardEntry(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $vendorDir = new VendorDir($root . '/vendor');
            $this->makeDirectory($vendorDir . '/acme/library');
            $locator = $this->createMock(PackageLocatorInterface::class);
            $locator->expects($this->once())->method('locate')
                ->with('acme/library', 'library', (string) $vendorDir)->willReturn(null);
            $parser = new PackageParser(new BasePath($root), $vendorDir, $locator);
            $metadata = new ComposerPackage([
                'name'    => 'acme/library',
                'version' => '1.0.0',
            ]);

            $this->assertThrows(PackageLocationException::class, fn() => $parser->locate($metadata), 'acme/library');
        });
    }
}
