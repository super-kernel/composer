<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Provider;

use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Composer\Contract\ComposerMetadataInterface;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\PackageReconcilerInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Exception\PackageCacheException;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\Composer\Manager\PackageManager;
use SuperKernel\Contract\PackageManagerInterface;
use SuperKernel\Contract\VendorDirInterface;
use Throwable;

use function array_keys;
use function is_array;
use function is_bool;
use function is_file;
use function is_readable;
use function sprintf;

#[FactoryProvider(PackageManagerInterface::class)]
final readonly class PackageManagerProvider
{
    /**
     * Reconciles installed dependencies and Root, then constructs a fresh manager from the restored packages.
     * Uses the supplied vendor directory to filter installation state without changing lock metadata.
     *
     * @return PackageManagerInterface Manager for the supplied metadata and its reconciled workspace
     *
     * @throws ComposerFileException    When supplied installation state cannot be read or is invalid
     * @throws PackageCacheException    When a reconciled package cannot be restored from its cache
     * @throws PackageLocationException When an installable package directory is missing
     */
    public function __invoke(
        PackageReconcilerInterface $reconciler,
        ComposerMetadataInterface  $metadata,
        VendorDirInterface         $vendorDir,
    ): PackageManagerInterface
    {
        $installation = self::installationState($vendorDir);
        $packages = [];

        foreach ($metadata->all() as $package) {
            if ($package->isDev() && !$installation['dev']
                && !isset($installation['versions'][$package->name()])) {
                continue;
            }

            $packages[] = $reconciler->reconcile($package);
        }

        $root = $reconciler->reconcile($metadata->root());
        $byName = [];
        $classes = [];
        $autoloadFiles = [];

        foreach ([
            $root,
            ...$packages,
            ] as $package) {
            $name = $package->name();
            $byName[$name] = $package;
            $classes[$name] = $reconciler->classes($name);
            $autoloadFiles[$name] = $reconciler->autoloadFiles($name);
        }

        $seen = [];
        $files = [];

        foreach (array_keys($byName) as $name) {
            self::appendAutoloadFiles($name, $byName, $autoloadFiles, $seen, $files);
        }

        return new PackageManager($root, $packages, $classes, array_keys($files));
    }

    /**
     * Reads only installation mode and membership; manifest and lock remain the package-data authority.
     *
     * @return array{dev: bool, versions: array<string, mixed>}
     *
     * @throws ComposerFileException When the generated installation state is unavailable or invalid
     */
    private static function installationState(VendorDirInterface $vendorDir): array
    {
        $path = $vendorDir . '/composer/installed.php';

        if (!is_file($path) || !is_readable($path)) {
            throw new ComposerFileException(sprintf('Composer installation state is unavailable: %s.', $path));
        }

        try {
            $state = (static fn(): mixed => require $path)();
        } catch (Throwable $exception) {
            throw new ComposerFileException(
                sprintf('Cannot read Composer installation state: %s.', $path),
                0,
                $exception,
            );
        }

        if (!is_array($state) || !is_array($state['root'] ?? null)
            || !is_bool($state['root']['dev'] ?? null) || !is_array($state['versions'] ?? null)) {
            throw new ComposerFileException(sprintf('Invalid Composer installation state: %s.', $path));
        }

        return [
            'dev'      => $state['root']['dev'],
            'versions' => $state['versions'],
        ];
    }

    /**
     * @param array<string, PackageInterface>       $packages
     * @param array<string, list<non-empty-string>> $autoloadFiles
     * @param array<string, true>                   $seen
     * @param array<non-empty-string, true>         $files
     */
    private static function appendAutoloadFiles(
        string $name,
        array  $packages,
        array  $autoloadFiles,
        array &$seen,
        array &$files,
    ): void
    {
        if (isset($seen[$name]) || !isset($packages[$name])) {
            return;
        }

        $seen[$name] = true;

        foreach ($packages[$name]->dependencies() as $dependency) {
            self::appendAutoloadFiles($dependency->name(), $packages, $autoloadFiles, $seen, $files);
        }

        foreach ($autoloadFiles[$name] as $file) {
            $files[$file] = true;
        }
    }
}
