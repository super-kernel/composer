<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Support;

use FilesystemIterator;
use JsonException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Random\RandomException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SuperKernel\Composer\Cache\PackageReconciler;
use SuperKernel\Composer\ClassMap\ClassMapBuilder;
use SuperKernel\Composer\Contract\PackageLocatorInterface;
use SuperKernel\Composer\Locator\PackageLocator;
use SuperKernel\Composer\Manager\PackageManager;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Metadata\PackageParser;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Path\FilesystemPath;
use SuperKernel\Composer\Path\VendorDir;
use SuperKernel\Composer\Provider\PackageManagerProvider;
use SuperKernel\Composer\Provider\VendorDirProvider;
use SuperKernel\Tokenizer\ClassNameParser;
use Throwable;

use function bin2hex;
use function dirname;
use function fclose;
use function file_put_contents;
use function is_dir;
use function is_link;
use function is_resource;
use function json_encode;
use function mkdir;
use function proc_close;
use function proc_open;
use function random_bytes;
use function rmdir;
use function sprintf;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const PHP_BINARY;

abstract class TestCase extends PhpUnitTestCase
{
    /**
     * Exercises the runtime adapter against a temporary workspace with explicit installation state.
     */
    final protected function readPackages(
        string                   $projectRoot,
        ?string                  $vendorDirectory = null,
        ?PackageLocatorInterface $packageLocator = null,
    ): PackageManager
    {
        $metadata = new ComposerMetadata(new BasePath($projectRoot));
        $basePath = new BasePath(FilesystemPath::canonicalDirectory($projectRoot));
        $vendorDir = null === $vendorDirectory
            ? new VendorDirProvider()($metadata, $basePath)
            : new VendorDir(FilesystemPath::absolute((string) $basePath, $vendorDirectory));

        $reconciler = new PackageReconciler(
            new PackageParser($basePath, $vendorDir, $packageLocator ?? new PackageLocator()),
            new ClassMapBuilder(new ClassNameParser()),
            $vendorDir,
            new CallbackScanProcessor(),
        );
        $this->writeInstallationState((string) $vendorDir);

        return new PackageManagerProvider()($reconciler, $metadata, $vendorDir);
    }

    /** @throws RuntimeException When the installation fixture cannot be written */
    final protected function writeInstallationState(string $vendorDirectory): void
    {
        $this->writeFile(
            $vendorDirectory . '/composer/installed.php',
            "<?php return ['root' => ['dev' => true], 'versions' => []];",
        );
    }

    /**
     * @template T of Throwable
     *
     * @param class-string<T>   $expected        Expected throwable class
     * @param callable(): mixed $callback        Operation expected to throw
     * @param string|null       $messageContains Required substring in the throwable message
     *
     * @return T The captured throwable
     *
     * @throws AssertionFailedError When no throwable, a different throwable, or a mismatched message is observed
     */
    final protected function assertThrows(
        string   $expected,
        callable $callback,
        ?string  $messageContains = null,
    ): Throwable
    {
        try {
            $callback();
        } catch (Throwable $throwable) {
            $this->assertInstanceOf($expected, $throwable);

            if (null !== $messageContains) {
                $this->assertStringContainsString($messageContains, $throwable->getMessage());
            }

            return $throwable;
        }

        $this->fail(sprintf('Expected %s to be thrown.', $expected));
    }

    /**
     * Executes a PHP body in an isolated child process.
     *
     * @param list<string> $phpOptions Additional PHP interpreter arguments for this child process only
     *
     * @return array{exitCode: int, stdout: string, stderr: string} Captured process result
     *
     * @throws RuntimeException When the script cannot be created, written, or started
     */
    final protected function runPhp(string $body, array $phpOptions = []): array
    {
        $script = tempnam(sys_get_temp_dir(), 'super-kernel-composer-test-');

        if (false === $script) {
            throw new RuntimeException('Unable to create an isolated PHP test script.');
        }

        try {
            $written = file_put_contents($script, "<?php\n\ndeclare(strict_types=1);\n\n" . $body);

            if (false === $written) {
                throw new RuntimeException('Unable to write an isolated PHP test script.');
            }

            $process = proc_open(
                [
                    PHP_BINARY,
                    '-d',
                    'display_errors=1',
                    ...$phpOptions,
                    $script,
                ],
                [
                    0 => [
                        'pipe',
                        'r',
                    ],
                    1 => [
                        'pipe',
                        'w',
                    ],
                    2 => [
                        'pipe',
                        'w',
                    ],
                ],
                $pipes,
            );

            if (!is_resource($process)) {
                throw new RuntimeException('Unable to start an isolated PHP test process.');
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            return [
                'exitCode' => $exitCode,
                'stdout'   => false === $stdout ? '' : $stdout,
                'stderr'   => false === $stderr ? '' : $stderr,
            ];
        } finally {
            unlink($script);
        }
    }

    /**
     * Creates an isolated directory, passes it to a callback, and removes it afterward.
     *
     * @template T
     *
     * @param callable(string): T $callback Operation executed with the temporary directory
     *
     * @return T Callback return value
     *
     * @throws RandomException  When a secure temporary-directory suffix cannot be generated
     * @throws RuntimeException When the temporary directory cannot be created
     * @throws Throwable        When the callback fails
     */
    final protected function withTemporaryDirectory(callable $callback): mixed
    {
        $directory = sys_get_temp_dir() . '/super-kernel-composer-' . bin2hex(random_bytes(12));

        if (!mkdir($directory, 0o700)) {
            throw new RuntimeException(sprintf('Unable to create temporary directory %s.', $directory));
        }

        try {
            return $callback($directory);
        } finally {
            $this->removeTemporaryDirectory($directory);
        }
    }

    /**
     * Creates a directory and any missing parents.
     *
     * @throws RuntimeException When the directory cannot be created
     */
    final protected function makeDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory %s.', $directory));
        }
    }

    /**
     * Writes a fixture file after creating its parent directory.
     *
     * @throws RuntimeException When the parent directory or file cannot be written
     */
    final protected function writeFile(string $path, string $contents = ''): void
    {
        $this->makeDirectory(dirname($path));

        if (false === file_put_contents($path, $contents)) {
            throw new RuntimeException(sprintf('Unable to write file %s.', $path));
        }
    }

    /**
     * Encodes and writes a human-readable JSON fixture.
     *
     * @throws JsonException    When the value cannot be encoded as JSON
     * @throws RuntimeException When the destination cannot be written
     */
    final protected function writeJson(string $path, mixed $value): void
    {
        $this->writeFile(
            $path,
            json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
    }

    private function removeTemporaryDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isLink() || $item->isFile()) {
                unlink($path);
            } else {
                rmdir($path);
            }
        }

        rmdir($directory);
    }
}
