<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use ReflectionClass;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Composer\Contract\DetectorInterface;
use SuperKernel\Composer\Detector\ComponentDetector;
use SuperKernel\Composer\Detector\ComposerScriptDetector;
use SuperKernel\Composer\Detector\NullDetector;
use SuperKernel\Composer\Detector\ScriptDetector;
use SuperKernel\Composer\Detector\WorkingDirectoryDetector;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Exception\ProjectRootNotFoundException;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Provider\BasePathProvider;
use SuperKernel\Composer\Provider\VendorDirProvider;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\BasePathInterface;
use Throwable;

use function chdir;
use function dirname;
use function getcwd;
use function getenv;
use function json_decode;
use function putenv;
use function realpath;
use function sprintf;
use function var_export;

use const JSON_THROW_ON_ERROR;

final class BasePathProviderTest extends TestCase
{
    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testFactoryBindingAndFirstSupportedDetectorWins(): void
    {
        $attributes = new ReflectionClass(BasePathProvider::class)->getAttributes(FactoryProvider::class);
        self::assertCount(1, $attributes);
        self::assertSame(BasePathInterface::class, $attributes[0]->newInstance()->className);

        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $unsupported = $this->createMock(DetectorInterface::class);
            $unsupported->expects(self::once())->method('support')->willReturn(false);
            $unsupported->expects(self::never())->method('projectDirectory');
            $supported = $this->createMock(DetectorInterface::class);
            $supported->expects(self::once())->method('support')->willReturn(true);
            $supported->expects(self::once())->method('projectDirectory')->willReturn($root);
            $later = $this->createMock(DetectorInterface::class);
            $later->expects(self::never())->method('support');
            $later->expects(self::never())->method('projectDirectory');
            $value = new BasePathProvider()([
                $unsupported,
                $supported,
                $later,
            ]);

            self::assertInstanceOf(BasePathInterface::class, $value);
            self::assertSame(realpath($root), (string)$value);
        });
    }

    public function testThrowsWhenNoDetectorSupportsTheEnvironment(): void
    {
        $unsupported = $this->createMock(DetectorInterface::class);
        $unsupported->method('support')->willReturn(false);
        $unsupported->expects(self::never())->method('projectDirectory');
        $this->assertThrows(ProjectRootNotFoundException::class, fn() => new BasePathProvider()([]));
        $this->assertThrows(ProjectRootNotFoundException::class, fn() => new BasePathProvider()([
            $unsupported,
        ]));
        $detector = new NullDetector();
        self::assertFalse($detector->support());
        self::assertSame('', $detector->projectDirectory());
        $this->assertThrows(ProjectRootNotFoundException::class, fn() => new BasePathProvider()([
            $detector,
        ]));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testTrustsTheDirectoryFromTheFirstSupportedDetector(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $directory = $root . '/detected';
            $supported = $this->createMock(DetectorInterface::class);
            $supported->expects(self::once())->method('support')->willReturn(true);
            $supported->expects(self::once())->method('projectDirectory')->willReturn($directory);
            $later = $this->createMock(DetectorInterface::class);
            $later->expects(self::never())->method('support');
            $later->expects(self::never())->method('projectDirectory');

            $value = new BasePathProvider()([
                $supported,
                $later,
            ]);

            self::assertSame($directory, (string)$value);
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testScriptDetectorsCaptureTheWorkingDirectoryInsteadOfTheEntryDirectory(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $workingDirectory = getcwd();
            self::assertIsString($workingDirectory);
            $originalScript = $_SERVER['SCRIPT_FILENAME'] ?? null;
            $originalMode = getenv('COMPOSER_DEV_MODE');
            $this->writeJson($root . '/application/composer.json', [
                'name' => 'fixture/application',
            ]);
            $this->writeJson($root . '/entry/composer.json', [
                'name' => 'fixture/entry',
            ]);
            $this->writeFile($root . '/entry/bin/console', '<?php');

            try {
                self::assertTrue(chdir($root . '/application'));
                $_SERVER['SCRIPT_FILENAME'] = $root . '/entry/bin/console';
                self::assertTrue(putenv('COMPOSER_DEV_MODE=0'));
                $composer = new ComposerScriptDetector();
                $script = new ScriptDetector();
                $working = new WorkingDirectoryDetector();

                self::assertTrue($composer->support());
                self::assertSame(realpath($root . '/application'), $composer->projectDirectory());
                self::assertSame(realpath($root . '/application'), $script->projectDirectory());
                self::assertSame(realpath($root . '/application'), $working->projectDirectory());
                self::assertSame(realpath($root . '/application'), (string)new BasePathProvider()());

                self::assertTrue(putenv('COMPOSER_DEV_MODE'));
                self::assertFalse(new ComposerScriptDetector()->support());
                self::assertSame(realpath($root . '/application'), (string)new BasePathProvider()());
                unset($_SERVER['SCRIPT_FILENAME']);
                self::assertSame(realpath($root . '/application'), (string)new BasePathProvider()());

                self::assertTrue(chdir($root . '/entry'));
                self::assertSame(realpath($root . '/application'), $working->projectDirectory());
                self::assertSame(realpath($root . '/application'), $script->projectDirectory());
                self::assertSame(realpath($root . '/entry'), (string)new BasePathProvider()());
            } finally {
                chdir($workingDirectory);
                putenv(false === $originalMode ? 'COMPOSER_DEV_MODE' : 'COMPOSER_DEV_MODE=' . $originalMode);

                if (null === $originalScript) {
                    unset($_SERVER['SCRIPT_FILENAME']);
                } else {
                    $_SERVER['SCRIPT_FILENAME'] = $originalScript;
                }
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPhpEntryPathsUseTheCurrentProjectWithCustomVendorDirectories(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $workingDirectory = getcwd();
            self::assertIsString($workingDirectory);
            $originalScript = $_SERVER['SCRIPT_FILENAME'] ?? null;
            $originalMode = getenv('COMPOSER_DEV_MODE');
            $application = $root . '/application';
            $lock = [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [],
                'packages-dev' => [],
            ];
            $this->writeJson($application . '/composer.lock', $lock);

            try {
                self::assertTrue(chdir($application));
                self::assertTrue(putenv('COMPOSER_DEV_MODE'));

                foreach ([
                    [
                        'dependencies/runtime',
                        $application . '/dependencies/runtime',
                    ],
                    [
                        '../shared/dependencies',
                        $root . '/shared/dependencies',
                    ],
                    [
                        $root . '/absolute/dependencies',
                        $root . '/absolute/dependencies',
                    ],
                ] as [$configured, $vendor]) {
                    $this->writeJson($application . '/composer.json', [
                        'name'   => 'fixture/application',
                        'config' => [
                            'vendor-dir' => $configured,
                        ],
                    ]);
                    $package = $vendor . '/fixture/console';
                    $this->writeJson($package . '/composer.json', [
                        'name' => 'fixture/console',
                    ]);
                    $this->writeJson($package . '/composer.lock', $lock);
                    $this->writeFile($package . '/bin/console', '<?php');

                    foreach ([
                        $configured . '/fixture/console/bin/console',
                        $package . '/bin/console',
                    ] as $script) {
                        $_SERVER['SCRIPT_FILENAME'] = $script;
                        $detector = new ScriptDetector();
                        $component = new ComponentDetector();
                        $basePath = new BasePathProvider()();
                        $metadata = new ComposerMetadata($basePath);
                        $vendorDir = new VendorDirProvider()($metadata, $basePath);

                        self::assertTrue($detector->support());
                        self::assertSame(realpath($application), $detector->projectDirectory());
                        self::assertTrue($component->support());
                        self::assertSame(realpath($application), $component->projectDirectory());
                        self::assertSame(realpath($application), (string)$basePath);
                        self::assertSame('fixture/application', $metadata->root()->name());
                        self::assertSame(realpath($vendor), (string)$vendorDir);
                    }
                }
            } finally {
                chdir($workingDirectory);
                putenv(false === $originalMode ? 'COMPOSER_DEV_MODE' : 'COMPOSER_DEV_MODE=' . $originalMode);

                if (null === $originalScript) {
                    unset($_SERVER['SCRIPT_FILENAME']);
                } else {
                    $_SERVER['SCRIPT_FILENAME'] = $originalScript;
                }
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testFilesystemDetectorsDoNotSearchParentOrComponentDirectories(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $workingDirectory = getcwd();
            self::assertIsString($workingDirectory);
            $originalScript = $_SERVER['SCRIPT_FILENAME'] ?? null;
            $originalMode = getenv('COMPOSER_DEV_MODE');
            $directory = $root . '/nested';
            $this->writeFile($root . '/composer.json', '{}');
            $this->writeFile($root . '/composer.lock', '{}');
            $this->writeFile($root . '/bin/console', '<?php');
            $this->makeDirectory($directory);

            try {
                self::assertTrue(chdir($directory));
                self::assertTrue(putenv('COMPOSER_DEV_MODE=1'));
                $_SERVER['SCRIPT_FILENAME'] = $root . '/bin/console';

                foreach ([
                    new ComposerScriptDetector(),
                    new ScriptDetector(),
                    new WorkingDirectoryDetector(),
                    new ComponentDetector(),
                ] as $detector) {
                    self::assertFalse($detector->support());
                    self::assertSame('', $detector->projectDirectory());
                }

                $this->assertThrows(ProjectRootNotFoundException::class, fn() => new BasePathProvider()());
                $this->writeFile($directory . '/composer.json', '{}');

                foreach ([
                    new ComposerScriptDetector(),
                    new ScriptDetector(),
                    new WorkingDirectoryDetector(),
                ] as $detector) {
                    self::assertTrue($detector->support());
                    self::assertSame(realpath($directory), $detector->projectDirectory());
                }

                $component = new ComponentDetector();
                self::assertFalse($component->support());
                self::assertSame('', $component->projectDirectory());
                $this->writeFile($directory . '/composer.lock', '{}');
                $component = new ComponentDetector();
                self::assertTrue($component->support());
                self::assertSame(realpath($directory), $component->projectDirectory());
            } finally {
                chdir($workingDirectory);
                putenv(false === $originalMode ? 'COMPOSER_DEV_MODE' : 'COMPOSER_DEV_MODE=' . $originalMode);

                if (null === $originalScript) {
                    unset($_SERVER['SCRIPT_FILENAME']);
                } else {
                    $_SERVER['SCRIPT_FILENAME'] = $originalScript;
                }
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDetectsTheRootBeforeParsingMetadataOrRequiringAnInstallation(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $workingDirectory = getcwd();
            self::assertIsString($workingDirectory);
            $this->writeFile($root . '/composer.json', '{');
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [],
                'packages-dev' => [],
            ]);
            try {
                self::assertTrue(chdir($root));
                $projectRoot = new BasePathProvider()([
                    new WorkingDirectoryDetector(),
                ]);

                self::assertSame(realpath($root), (string)$projectRoot);
                $this->assertThrows(ComposerFileException::class, fn() => new ComposerMetadata($projectRoot));

                $this->writeJson($root . '/composer.json', [
                    'name'   => 'fixture/application',
                    'config' => [
                        'vendor-dir' => 'custom/install',
                    ],
                ]);
                $metadata = new ComposerMetadata($projectRoot);
                $vendorDir = new VendorDirProvider()($metadata, $projectRoot);

                self::assertSame('fixture/application', $metadata->root()->name());
                self::assertSame(realpath($root) . '/custom/install', (string)$vendorDir);
                self::assertDirectoryDoesNotExist((string)$vendorDir);
            } finally {
                chdir($workingDirectory);
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testPharDetectorProvidesTheArchiveRootAndReadableNativeMetadata(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $archive = $root . '/application.phar';
            $source = realpath(dirname(__DIR__, 2) . '/src');
            self::assertIsString($source);
            $basePathSource = new ReflectionClass(BasePathInterface::class)->getFileName();
            self::assertIsString($basePathSource);
            $built = $this->runPhp(sprintf(<<<'PHP'
                $archive = new Phar(%s);
                $archive->buildFromDirectory(%s, '/\.php$/');
                $contractSource = dirname(%s);
                $archive->addFile($contractSource . '/BasePathInterface.php', 'Contract/BasePathInterface.php');
                $archive->addFile($contractSource . '/VendorDirInterface.php', 'Contract/VendorDirInterface.php');
                $archive['composer.json'] = json_encode([
                    'name' => 'fixture/application',
                    'config' => [
                        'vendor-dir' => 'dependencies',
                    ],
                ], JSON_THROW_ON_ERROR);
                $archive['composer.lock'] = json_encode([
                    'content-hash' => '0123456789abcdef0123456789abcdef',
                    'packages' => [],
                    'packages-dev' => [],
                ], JSON_THROW_ON_ERROR);
                $archive['probe.php'] = <<<'PROBE'
                <?php
                require __DIR__ . '/Contract/BasePathInterface.php';
                require __DIR__ . '/Contract/VendorDirInterface.php';
                spl_autoload_register(static function (string $class): void {
                    $prefix = 'SuperKernel\\Composer\\';
                    if (str_starts_with($class, $prefix)) {
                        require __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                    }
                });
                $detector = new SuperKernel\Composer\Detector\NullDetector();
                $root = new SuperKernel\Composer\Provider\BasePathProvider()();
                $metadata = new SuperKernel\Composer\Metadata\ComposerMetadata($root);
                $vendorDir = new SuperKernel\Composer\Provider\VendorDirProvider()($metadata, $root);
                echo json_encode([
                    'supported' => $detector->support(),
                    'detectorInterface' => $detector instanceof SuperKernel\Composer\Contract\DetectorInterface,
                    'basePath' => $root instanceof SuperKernel\Contract\BasePathInterface,
                    'root' => (string)$root,
                    'vendor' => (string)$vendorDir,
                    'vendorInterface' => $vendorDir instanceof SuperKernel\Contract\VendorDirInterface,
                    'name' => $metadata->root()->name(),
                ], JSON_THROW_ON_ERROR);
                PROBE;
                $archive->setStub(Phar::createDefaultStub('probe.php'));
                PHP, var_export($archive, true), var_export($source, true), var_export($basePathSource, true)), [
                '-d',
                'phar.readonly=0',
            ]);
            self::assertSame(0, $built['exitCode'], $built['stderr'] . $built['stdout']);
            $result = $this->runPhp('require ' . var_export($archive, true) . ';');
            self::assertSame(0, $result['exitCode'], $result['stderr'] . $result['stdout']);
            $data = json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR);

            self::assertTrue($data['supported']);
            self::assertTrue($data['detectorInterface']);
            self::assertTrue($data['basePath']);
            self::assertSame('phar://' . realpath($archive), $data['root']);
            self::assertSame($data['root'] . '/dependencies', $data['vendor']);
            self::assertTrue($data['vendorInterface']);
            self::assertSame('fixture/application', $data['name']);
        });
    }
}
