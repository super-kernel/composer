<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use ReflectionClass;
use stdClass;
use SuperKernel\Composer\Cache\PackageReconciler;
use SuperKernel\Composer\ClassMap\ClassMapBuilder;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Exception\LockFileException;
use SuperKernel\Composer\Exception\PackageCacheException;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\Composer\Locator\PackageLocator;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Metadata\ComposerPackage;
use SuperKernel\Composer\Metadata\PackageParser;
use SuperKernel\Composer\Model\Package;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Path\VendorDir;
use SuperKernel\Composer\Tests\Support\CallbackScanProcessor;
use SuperKernel\Composer\Tests\Support\RecordingClassNameParser;
use SuperKernel\Composer\Tests\Support\RestoreOnlyScanProcessor;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\ScanIsolation\Contract\ScanProcessorInterface;
use SuperKernel\ScanIsolation\Exception\ScanProcessorUnavailableException;
use Throwable;

use function file_get_contents;
use function glob;
use function hash;
use function rename;
use function rmdir;
use function serialize;
use function str_replace;
use function substr;
use function symlink;
use function unlink;
use function unserialize;

final class PackageReconcilerTest extends TestCase
{
    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testColdPackageIsPublishedInsideTheProcessorAndRestoredAfterItReturns(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $this->writeFile($root . '/vendor/.cache/packages.ser', 'annotation reference stub');
            $this->writeFile($root . '/vendor/.cache/annotations/acme/a.ser', 'annotation cache');
            $processor = new CallbackScanProcessor();
            $processor->afterScan = static function () use ($root): void {
                self::assertFileExists($root . '/vendor/.cache/classes/acme/a.ser');
            };
            $reconciler = $this->reconciler($root, processor: $processor);
            $package = $reconciler->reconcile($this->package('a', 'a-reference'));

            self::assertInstanceOf(Package::class, $package);
            self::assertSame('acme/a', $package->name());
            self::assertSame('a-reference', $package->reference());

            self::assertSame(1, $processor->calls);
            self::assertSame(1, $processor->probes);
            self::assertSame([
                'Acme\\A\\Stable' => $root . '/vendor/acme/a/src/A.source',
            ], $reconciler->classes('acme/a'));
            self::assertFileExists($root . '/vendor/.cache/classes/acme/a.ser');
            self::assertSame('annotation reference stub', file_get_contents($root . '/vendor/.cache/packages.ser'));
            self::assertSame(
                'annotation cache',
                file_get_contents($root . '/vendor/.cache/annotations/acme/a.ser'),
            );
            self::assertDirectoryDoesNotExist($root . '/vendor/.runtime');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testEqualReferencesReuseThePackageUntilTheReferenceChanges(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $parser = new RecordingClassNameParser();
            $processor = new CallbackScanProcessor();
            $reconciler = $this->reconciler($root, $parser, $processor);
            $first = $reconciler->reconcile($this->package('a', 'a-reference'));
            $original = file_get_contents($root . '/vendor/.cache/classes/acme/a.ser');
            $parser->files = [];
            $this->writeFile(
                $root . '/vendor/acme/a/src/A.source',
                '<?php namespace Acme\\A; final class Changed {}',
            );
            $reused = $reconciler->reconcile($this->package('a', 'a-reference', '2.0.0'));

            self::assertNotSame($first, $reused);
            self::assertSame('1.0.0', $reused->version());
            self::assertSame($original, file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));
            self::assertSame([], $parser->files);

            self::assertSame(2, $processor->calls);

            $updated = $reconciler->reconcile($this->package('a', 'new-reference', '2.0.0'));
            self::assertSame('2.0.0', $updated->version());
            self::assertNotSame($original, file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));
            self::assertSame('new-reference', $updated->reference());

            self::assertSame([
                'Acme\\A\\Changed' => $root . '/vendor/acme/a/src/A.source',
            ], $reconciler->classes('acme/a'));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testNullReferencesCompareExactlyAndRemainReusable(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $parser = new RecordingClassNameParser();
            $reconciler = $this->reconciler($root, $parser);
            $reconciler->reconcile($this->package('a', null));
            $original = file_get_contents($root . '/vendor/.cache/classes/acme/a.ser');
            $parser->files = [];
            $package = $reconciler->reconcile($this->package('a', null, '2.0.0'));

            self::assertNull($package->reference());
            self::assertSame('1.0.0', $package->version());
            self::assertSame($original, file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));
            self::assertSame([], $parser->files);


            $updated = $reconciler->reconcile($this->package('a', 'new-reference', '2.0.0'));
            self::assertSame('2.0.0', $updated->version());
            self::assertNotSame($original, file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));

        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCachedPackagesFailWhenTheirLocatedDirectoryDisappears(): void
    {
        foreach ([
            new CallbackScanProcessor(),
            new RestoreOnlyScanProcessor(),
        ] as $processor) {
            $this->withTemporaryDirectory(function (
                string $root,
            ) use ($processor): void {
                $this->workspace($root);

                $metadata = $this->package('a', 'a-reference');
                $this->reconciler($root)->reconcile($metadata);
                $original = file_get_contents($root . '/vendor/.cache/classes/acme/a.ser');
                self::assertTrue(rename($root . '/vendor/acme/a', $root . '/removed-package'));
                $parser = new RecordingClassNameParser();
                $reconciler = $this->reconciler($root, $parser, $processor);
                $exception = $this->assertThrows(
                    PackageLocationException::class,
                    fn() => $reconciler->reconcile($metadata),
                    'acme/a',
                );

                self::assertStringContainsString($root . '/vendor/acme/a', $exception->getMessage());
                self::assertSame([], $parser->files);

                self::assertSame($original, file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));
            });
        }
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsAMissingCachedLocationAfterTheVendorEntryIsRetargeted(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);
            $entry = $root . '/vendor/acme/a';
            $oldSource = $root . '/old-source';
            self::assertTrue(rename($entry, $oldSource));
            self::assertTrue(symlink($oldSource, $entry));

            $parser = new RecordingClassNameParser();
            $reconciler = $this->reconciler($root, $parser);
            $metadata = $this->package('a', 'a-reference');
            $reconciler->reconcile($metadata);
            $parser->files = [];
            $newSource = $root . '/new-source';
            self::assertTrue(rename($oldSource, $newSource));
            self::assertTrue(unlink($entry));
            self::assertTrue(symlink($newSource, $entry));
            $exception = $this->assertThrows(
                PackageLocationException::class,
                fn() => $reconciler->reconcile($metadata),
                'acme/a',
            );

            self::assertStringContainsString($oldSource, $exception->getMessage());
            self::assertSame([], $parser->files);

        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRootIsReparsedAndRescannedOnEveryInvocation(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $reconciler = $this->reconciler($root);
            $first = $reconciler->reconcile(new ComposerMetadata(new BasePath($root))->root());
            $this->writeFile($root . '/src/Added.source', '<?php namespace Application; final class Added {}');
            $this->writeJson($root . '/composer.json', [
                'name'     => 'acme/project',
                'version'  => '2.0.0',
                'autoload' => [
                    'classmap' => [
                        'src/',
                    ],
                ],
            ]);
            $second = $reconciler->reconcile(new ComposerMetadata(new BasePath($root))->root());

            self::assertNull($first->reference());
            self::assertNull($second->reference());
            self::assertNull($first->version());
            self::assertSame('2.0.0', $second->version());

            self::assertArrayHasKey('Application\\Added', $reconciler->classes('acme/project'));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testEachPackagePublishesIndependentlyAndRootDoesNotSealTheReconciler(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $processor = new CallbackScanProcessor();
            $reconciler = $this->reconciler($root, processor: $processor);
            $reconciler->reconcile(new ComposerMetadata(new BasePath($root))->root());
            $reconciler->reconcile($this->package('b', 'b-reference'));
            $reconciler->reconcile($this->package('a', 'a-reference'));

            self::assertSame(3, $processor->calls);
            self::assertFileExists($root . '/vendor/.cache/classes/acme/project.ser');
            self::assertFileExists($root . '/vendor/.cache/classes/acme/a.ser');
            self::assertFileExists($root . '/vendor/.cache/classes/acme/b.ser');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testAFormerRootCacheCannotBecomeALockedPackageWithTheSameNullReference(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);
            $this->writeJson($root . '/composer.json', [
                'name' => 'acme/a',
            ]);

            $reconciler = $this->reconciler($root);
            $reconciler->reconcile(new ComposerMetadata(new BasePath($root))->root());
            $original = file_get_contents($root . '/vendor/.cache/classes/acme/a.ser');
            $package = $reconciler->reconcile($this->package('a', null));

            self::assertSame('1.0.0', $package->version());
            self::assertNotSame($original, file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));
            self::assertSame($root . '/vendor/acme/a', $package->locatedPath());

        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testSelfPackageUsesLockedMetadataAfterCacheReuse(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);
            $source = $root . '/vendor/super-kernel/composer/src/SelfDefinition.php';
            $this->writeFile($source, '<?php namespace SuperKernel\\Composer; final class SelfDefinition {}');
            $metadata = new ComposerPackage([
                'name'     => 'super-kernel/composer',
                'version'  => 'dev-main',
                'autoload' => [
                    'psr-4' => [
                        'SuperKernel\\Composer\\' => 'src/',
                    ],
                ],
            ], development: true);

            $parser = new RecordingClassNameParser();
            $reconciler = $this->reconciler($root, $parser);
            $first = $reconciler->reconcile($metadata);
            $parser->files = [];
            $restored = $reconciler->reconcile($metadata);

            self::assertSame(Package::class, $restored::class);
            self::assertNotSame($first, $restored);
            self::assertTrue($restored->isLockedDevPackage());
            self::assertNull($restored->reference());
            self::assertSame([], $parser->files);
            self::assertSame([
                'SuperKernel\\Composer\\SelfDefinition' => $source,
            ], $reconciler->classes('super-kernel/composer'));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testInvalidAndLegacyCachesRequireAProcessingPass(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $reconciler = $this->reconciler($root);
            $metadata = $this->package('a', 'a-reference');
            $reconciler->reconcile($metadata);
            $original = file_get_contents($root . '/vendor/.cache/classes/acme/a.ser');
            $record = unserialize($original);
            self::assertIsArray($record);
            $legacy = [
                'schema' => 2,
                ...$record,
            ];
            unset($legacy['checksum']);
            $legacy['checksum'] = hash('sha256', serialize($legacy));

            foreach ([
                'corruption',
                'checksum',
                'legacy',
            ] as $failure) {
                $this->writeFile($root . '/vendor/.cache/classes/acme/a.ser', match ($failure) {
                    'corruption' => 'corrupted',
                    'checksum'   => str_replace('a-reference', 'x-reference', $original),
                    'legacy'     => serialize($legacy),
                });
                $restoreOnly = $this->reconciler($root, processor: new RestoreOnlyScanProcessor());
                $this->assertThrows(PackageCacheException::class, fn() => $restoreOnly->reconcile($metadata));
                $restored = $reconciler->reconcile($metadata);

                self::assertSame('a-reference', $restored->reference());
                self::assertSame($original, file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testObjectLayoutAndAllowListFailuresRequireRebuilding(): void
    {
        $this->withTemporaryDirectory(function (string $root): void {
            $this->workspace($root);
            $parser = new RecordingClassNameParser();
            $reconciler = $this->reconciler($root, $parser);
            $metadata = $this->package('a', 'a-reference');
            $reconciler->reconcile($metadata);
            $path = $root . '/vendor/.cache/classes/acme/a.ser';
            $original = file_get_contents($path);
            $record = unserialize($original);
            unset($record['checksum']);

            // This fixture was serialized at 60d3d39, before the Package layout changed.
            $old = str_replace(
                serialize('/cache-layout-fixture/vendor/acme/a'),
                serialize($root . '/vendor/acme/a'),
                file_get_contents(__DIR__ . '/../Fixture/package-with-fingerprint.ser'),
            );
            $oldRecord = $record;
            $oldRecord['package'] = 'old-package-marker';
            $payload = str_replace(serialize('old-package-marker'), $old, serialize($oldRecord));
            $oldCache = 'a:5:{' . substr($payload, 5, -1)
                . serialize('checksum') . serialize(hash('sha256', $payload)) . '}';

            $reflection = new ReflectionClass(Package::class);
            $incomplete = $reflection->newInstanceWithoutConstructor();
            foreach ($reflection->getProperties() as $property) {
                if ('composerType' !== $property->getName()) {
                    $property->setValue($incomplete, $property->getValue($record['package']));
                }
            }
            $invalid = [
                'previous layout' => $oldCache,
            ];
            foreach ([
                'missing property'   => $incomplete,
                'outside allow-list' => new stdClass(),
            ] as $name => $package) {
                $candidate = $record;
                $candidate['package'] = $package;
                $candidate['checksum'] = hash('sha256', serialize($candidate));
                $invalid[$name] = serialize($candidate);
            }

            foreach ($invalid as $name => $contents) {
                $this->writeFile($path, $contents);
                $restoreOnly = $this->reconciler($root, processor: new RestoreOnlyScanProcessor());
                $this->assertThrows(PackageCacheException::class, fn() => $restoreOnly->reconcile($metadata));
                self::assertSame($contents, file_get_contents($path), $name);
                $parser->files = [];
                $reconciler->reconcile($metadata);
                self::assertNotEmpty($parser->files, $name);
                self::assertSame($original, file_get_contents($path), $name);
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReturnsTheLatestPublishedInstanceAfterTheProcessorFinishes(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $processor = new CallbackScanProcessor();
            $processor->afterScan = function () use ($root): void {
                $this->reconciler($root)->reconcile($this->package('a', 'latest-reference', '2.0.0'));
            };
            $reconciler = $this->reconciler($root, processor: $processor);
            $package = $reconciler->reconcile($this->package('a', 'initial-reference'));

            self::assertSame('latest-reference', $package->reference());
            self::assertSame('2.0.0', $package->version());

        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPostProcessingReadFailureDoesNotReturnTheCallbackResult(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $processor = new CallbackScanProcessor();
            $processor->afterScan = static function () use ($root): void {
                self::assertTrue(unlink($root . '/vendor/.cache/classes/acme/a.ser'));
            };
            $reconciler = $this->reconciler($root, processor: $processor);

            $this->assertThrows(
                PackageCacheException::class,
                fn() => $reconciler->reconcile($this->package('a', 'a-reference')),
                'Unable to read',
            );

            self::assertFileDoesNotExist($root . '/vendor/.cache/classes/acme/a.ser');
            self::assertSame(1, $processor->calls);
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPostProcessingCorruptionIsNotRepairedOutsideTheProcessor(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $processor = new CallbackScanProcessor();
            $processor->afterScan = function () use ($root): void {
                $this->writeFile($root . '/vendor/.cache/classes/acme/a.ser', 'corrupted');
            };
            $reconciler = $this->reconciler($root, processor: $processor);

            $this->assertThrows(
                PackageCacheException::class,
                fn() => $reconciler->reconcile($this->package('a', 'a-reference')),
            );

            self::assertSame('corrupted', file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));
            self::assertSame(1, $processor->calls);
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRestoreOnlyProcessorRestoresPublishedPackagesAndRejectsMissingOnes(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $restoreOnly = $this->reconciler($root, processor: new RestoreOnlyScanProcessor());
            $metadata = $this->package('a', 'a-reference');
            $this->assertThrows(PackageCacheException::class, fn() => $restoreOnly->reconcile($metadata));
            self::assertDirectoryDoesNotExist($root . '/vendor/.cache');
            $this->reconciler($root)->reconcile($metadata);
            $restored = $restoreOnly->reconcile($metadata);

            self::assertSame('a-reference', $restored->reference());

            self::assertArrayHasKey('Acme\\A\\Stable', $restoreOnly->classes('acme/a'));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testUnsupportedProcessorIsRejectedBeforeAnyCacheAccess(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $processor = new CallbackScanProcessor();
            $processor->supported = false;
            $reconciler = $this->reconciler($root, processor: $processor);

            $this->assertThrows(
                ScanProcessorUnavailableException::class,
                fn() => $reconciler->reconcile($this->package('a', 'a-reference')),
            );
            self::assertSame(1, $processor->probes);
            self::assertSame(0, $processor->calls);

            self::assertDirectoryDoesNotExist($root . '/vendor/.cache');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testFailedPackagesPreservePreviouslyPublishedPackageFiles(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);

            $reconciler = $this->reconciler($root);
            $reconciler->reconcile($this->package('a', 'a-reference'));
            $original = file_get_contents($root . '/vendor/.cache/classes/acme/a.ser');
            $this->makeDirectory($root . '/vendor/.cache/classes/acme/b.ser');
            $this->assertThrows(
                PackageCacheException::class,
                fn() => $reconciler->reconcile($this->package('b', 'b-reference')),
                'Unable to write',
            );
            self::assertSame($original, file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));
            self::assertSame([], glob($root . '/vendor/.cache/classes/acme/.classes-*'));
            self::assertTrue(rmdir($root . '/vendor/.cache/classes/acme/b.ser'));
            $invalid = new ComposerPackage([
                'name'     => 'acme/b',
                'version'  => '1.0.0',
                'autoload' => [
                    'classmap' => [
                        '../escape',
                    ],
                ],
            ]);
            $this->assertThrows(LockFileException::class, fn() => $reconciler->reconcile($invalid));
            self::assertSame($original, file_get_contents($root . '/vendor/.cache/classes/acme/a.ser'));
            self::assertFileDoesNotExist($root . '/vendor/.cache/classes/acme/b.ser');
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsACacheDirectorySymlinkWithoutWritingThroughIt(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->workspace($root);
            $this->makeDirectory($root . '/outside');
            $this->writeFile($root . '/vendor/.cache/packages.ser', 'annotation reference stub');
            self::assertTrue(symlink($root . '/outside', $root . '/vendor/.cache/classes'));
            $reconciler = $this->reconciler($root);

            $this->assertThrows(
                PackageCacheException::class,
                fn() => $reconciler->reconcile($this->package('a', null)),
                'not a regular directory',
            );
            self::assertDirectoryDoesNotExist($root . '/outside/acme');
            self::assertSame('annotation reference stub', file_get_contents($root . '/vendor/.cache/packages.ser'));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCopiedCacheDoesNotReuseARecordFromAnotherVendorDirectory(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $firstRoot = $root . '/first';
            $secondRoot = $root . '/second';
            $this->workspace($firstRoot);
            $this->workspace($secondRoot);

            $metadata = $this->package('a', 'a-reference');
            $this->reconciler($firstRoot)->reconcile($metadata);
            $original = file_get_contents($firstRoot . '/vendor/.cache/classes/acme/a.ser');
            $this->writeFile($secondRoot . '/vendor/.cache/classes/acme/a.ser', $original);
            $restoreOnly = $this->reconciler($secondRoot, processor: new RestoreOnlyScanProcessor());
            $this->assertThrows(PackageCacheException::class, fn() => $restoreOnly->reconcile($metadata));
            $restored = $this->reconciler($secondRoot)->reconcile($metadata);

            self::assertSame($secondRoot . '/vendor/acme/a', $restored->locatedPath());
            self::assertNotSame($original, file_get_contents($secondRoot . '/vendor/.cache/classes/acme/a.ser'));

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
        $this->writeJson($root . '/composer.lock', [
            'content-hash' => '0123456789abcdef0123456789abcdef',
            'packages'     => [],
            'packages-dev' => [],
        ]);
        $this->writeFile($root . '/src/Root.source', '<?php namespace Application; final class Root {}');
        $this->writeFile($root . '/vendor/acme/a/src/A.source', '<?php namespace Acme\\A; final class Stable {}');
        $this->writeFile($root . '/vendor/acme/b/src/B.source', '<?php namespace Acme\\B; final class Stable {}');
    }

    private function package(string $name, ?string $reference, string $version = '1.0.0'): ComposerPackageInterface
    {
        return new ComposerPackage([
            'name'    => 'acme/' . $name,
            'version' => $version,
            'source'  => [
                'reference' => $reference,
            ],
            'autoload' => [
                'classmap' => [
                    'src/',
                ],
            ],
        ]);
    }

    private function reconciler(
        string                    $root,
        ?RecordingClassNameParser $parser = null,
        ?ScanProcessorInterface   $processor = null,
    ): PackageReconciler
    {
        $vendorDir = new VendorDir($root . '/vendor');

        return new PackageReconciler(
            new PackageParser(new BasePath($root), $vendorDir, new PackageLocator()),
            new ClassMapBuilder($parser ?? new RecordingClassNameParser()),
            $vendorDir,
            $processor ?? new CallbackScanProcessor(),
        );
    }
}
