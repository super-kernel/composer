<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use JsonException;
use RuntimeException;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Provider\BasePathProvider;
use SuperKernel\Composer\Provider\VendorDirProvider;
use SuperKernel\Composer\Tests\Support\TestCase;
use Throwable;

use function array_keys;
use function dirname;
use function explode;
use function getenv;
use function implode;
use function json_decode;
use function php_ini_scanned_files;
use function putenv;
use function sprintf;
use function trim;
use function var_export;

use const JSON_THROW_ON_ERROR;

final class AutoloadEntryTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testComposerLoadingDoesNotInitializeThePackageGraphOrReplaceTheLoader(): void
    {
        $basePath = new BasePathProvider()();
        $metadata = new ComposerMetadata($basePath);
        $entry = new VendorDirProvider()($metadata, $basePath) . '/autoload.php';
        $result = $this->runIsolatedPhp(sprintf(<<<'PHP'
            $first = require %s;
            $second = require %s;

            echo json_encode([
                'loaderType' => get_debug_type($first),
                'sameLoader' => $first === $second,
                'soleComposerLoader' => spl_autoload_functions() === [[$first, 'loadClass']],
                'managerLoaded' => class_exists(SuperKernel\Composer\Manager\PackageManager::class, false),
                'reconcilerLoaded' => class_exists(SuperKernel\Composer\Cache\PackageReconciler::class, false),
                'superKernelLoaderLoaded' => class_exists(SuperKernel\Autoloader\AutoLoader::class, false),
            ], JSON_THROW_ON_ERROR);
            PHP, var_export($entry, true), var_export($entry, true)));

        self::assertSame('Composer\\Autoload\\ClassLoader', $result['loaderType']);
        self::assertTrue($result['sameLoader']);
        self::assertTrue($result['soleComposerLoader']);
        self::assertFalse($result['managerLoaded']);
        self::assertFalse($result['reconcilerLoaded']);
        self::assertFalse($result['superKernelLoaderLoaded']);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testProcessFallbackReachesLaterCallbacksAfterComposerLoading(): void
    {
        $this->assertReentryAfterComposerLoading('pcntl_fork', 'ProcessScanProcessor');
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testExecFallbackReachesLaterCallbacksAfterComposerLoading(): void
    {
        $this->assertReentryAfterComposerLoading('pcntl_fork,proc_open', 'ExecScanProcessor');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException    When the child process does not emit valid JSON
     * @throws RuntimeException When the child process cannot be executed
     */
    private function runIsolatedPhp(string $body): array
    {
        $result = $this->runPhp($body);

        $this->assertSame(
            0,
            $result['exitCode'],
            "Isolated PHP process failed:\n" . $result['stderr'] . $result['stdout'],
        );

        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded, 'Expected isolated process JSON output.');

        return $decoded;
    }

    /** @throws Throwable When fixture setup or a supplied test callback fails */
    private function assertReentryAfterComposerLoading(string $disabledFunctions, string $expectedProcessor): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ) use ($disabledFunctions, $expectedProcessor): void {
            $this->writeJson($root . '/composer.json', [
                'name' => 'fixture/application',
            ]);
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [],
                'packages-dev' => [],
            ]);
            $this->writeInstallationState($root . '/vendor');
            $this->writeFile($root . '/99-scan-test.ini', 'disable_functions=' . $disabledFunctions . "\n");
            $directories = [];

            foreach (explode(',', php_ini_scanned_files() ?: '') as $file) {
                if ('' !== trim($file)) {
                    $directories[dirname(trim($file))] = true;
                }
            }

            $directories[$root] = true;
            $previous = getenv('PHP_INI_SCAN_DIR');
            self::assertTrue(putenv('PHP_INI_SCAN_DIR=' . implode(':', array_keys($directories))));

            try {
                $basePath = new BasePathProvider()();
                $metadata = new ComposerMetadata($basePath);
                $entry = new VendorDirProvider()($metadata, $basePath) . '/autoload.php';
                $result = $this->runIsolatedPhp(sprintf(<<<'PHP'
                    $loader = require %s;
                    $directory = %s;
                    $basePath = new SuperKernel\Composer\Path\BasePath($directory);
                    $metadata = new SuperKernel\Composer\Metadata\ComposerMetadata($basePath);
                    $vendorDir = new SuperKernel\Composer\Provider\VendorDirProvider()($metadata, $basePath);
                    $parser = new SuperKernel\Composer\Metadata\PackageParser(
                        $basePath,
                        $vendorDir,
                        new SuperKernel\Composer\Locator\PackageLocator(),
                    );
                    $processor = new SuperKernel\ScanIsolation\Provider\ScanProcessorProvider()();
                    $reconciler = new SuperKernel\Composer\Cache\PackageReconciler(
                        $parser,
                        new SuperKernel\Composer\ClassMap\ClassMapBuilder(
                            new SuperKernel\Tokenizer\ClassNameParser(),
                        ),
                        $vendorDir,
                        $processor,
                    );
                    $provider = new SuperKernel\Composer\Provider\PackageManagerProvider();
                    $first = $provider($reconciler, $metadata, $vendorDir);
                    $second = $provider($reconciler, $metadata, $vendorDir);
                    foreach ([1, 2] as $index) {
                        $processor->execute(static function () use ($directory, $index): void {
                            file_put_contents($directory . '/' . $index, getmypid() . "\n", FILE_APPEND);
                        });
                    }

                    echo json_encode([
                        'processor' => $processor::class,
                        'callbacks' => [
                            is_file($directory . '/1') ? count(file($directory . '/1')) : 0,
                            is_file($directory . '/2') ? count(file($directory . '/2')) : 0,
                        ],
                        'sameGraph' => $first->allClasses() === $second->allClasses(),
                        'soleLoader' => spl_autoload_functions() === [[$loader, 'loadClass']],
                    ], JSON_THROW_ON_ERROR);
                    PHP, var_export($entry, true), var_export($root, true)));
            } finally {
                putenv(false === $previous ? 'PHP_INI_SCAN_DIR' : 'PHP_INI_SCAN_DIR=' . $previous);
            }

            self::assertSame('SuperKernel\\ScanIsolation\\Processor\\' . $expectedProcessor, $result['processor']);
            self::assertSame([
                1,
                1,
            ], $result['callbacks']);
            self::assertTrue($result['sameGraph']);
            self::assertTrue($result['soleLoader']);
        });
    }
}
