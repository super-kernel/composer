<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SuperKernel\Composer\Contract\DependencyInterface;
use SuperKernel\Composer\Tests\Support\TestCase;
use Throwable;

use function array_find;
use function dirname;
use function file_get_contents;
use function is_string;
use function realpath;
use function sprintf;
use function str_contains;

final class ForbiddenMetadataTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testDerivesMetadataOnlyFromTheRootManifestAndLockFile(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->makeDirectory($root . '/vendor/composer');
            $this->makeDirectory($root . '/vendor/acme/package');
            $this->writeJson($root . '/composer.json', [
                'name'    => 'acme/project',
                'require' => [
                    'acme/package' => '^1.0',
                    'ext-json'     => '*',
                    'php'          => '^8.4',
                ],
            ]);
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [
                    [
                    'name'    => 'acme/package',
                    'version' => '1.2.3',
                    'type'    => 'library',
                    'require' => [
                        'ext-curl' => '^8.0',
                    ],
                ],
                ],
                'packages-dev' => [],
            ]);
            $this->writeFile(
                $root . '/vendor/composer/installed.php',
                '<?php throw new RuntimeException("installed.php must not execute");',
            );
            $this->writeJson($root . '/vendor/composer/installed.json', [
                'packages' => [
                    [
                    'name'    => 'acme/package',
                    'version' => '999.0.0',
                    'type'    => 'metapackage',
                ],
                ],
            ]);
            $this->writeJson($root . '/vendor/acme/package/composer.json', [
                'name'    => 'different/name',
                'version' => '888.0.0',
                'type'    => 'custom-installer',
                'require' => [
                    'ext-ignored' => '*',
                ],
            ]);

            $metadata = $this->readPackages($root);
            $package = $metadata->get('acme/package');
            $dependencies = $metadata->root()->dependencies();

            $this->assertSame('^1.0', $this->dependency($dependencies, 'acme/package')?->constraint());
            $this->assertNull($this->dependency($dependencies, 'ext-json'));
            $this->assertNull($this->dependency($dependencies, 'php'));
            $this->assertSame('acme/package', $package?->name());
            $this->assertSame('1.2.3', $package?->version());
            $this->assertSame('library', $package?->composerType());
            $this->assertSame(realpath($root . '/vendor/acme/package'), $package?->locatedPath());
            $this->assertSame('ext-curl', $package?->environmentRequirements()[0]->name());
            $this->assertSame('ext-json', $metadata->root()->environmentRequirements()[0]->name());
            $this->assertCount(3, $metadata->environmentRequirements());
        });
    }

    public function testRestrictsInstallationStateReadingToTheManagerAssemblyBoundary(): void
    {
        $source = realpath(dirname(__DIR__, 2) . '/src');
        $this->assertNotFalse($source);
        $forbidden = [
            'installed.php',
            'installed.json',
            'InstalledVersions.php',
            'Composer\\InstalledVersions',
        ];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            if ($source . '/Provider/PackageManagerProvider.php' === $file->getPathname()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $this->assertTrue(is_string($contents));

            foreach ($forbidden as $needle) {
                $this->assertFalse(
                    str_contains($contents, $needle),
                    sprintf('%s contains forbidden metadata fallback %s.', $file->getPathname(), $needle),
                );
            }
        }
    }

    /**
     * @param list<DependencyInterface> $dependencies
     */
    private function dependency(array $dependencies, string $name): ?DependencyInterface
    {
        return array_find(
            $dependencies,
            static fn(DependencyInterface $dependency): bool => $dependency->name() === $name,
        );
    }
}
