<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Cache;

use SuperKernel\Attribute\Provider;
use SuperKernel\Composer\ClassMap\ClassMapBuilder;
use SuperKernel\Composer\Constants\AutoloadScope;
use SuperKernel\Composer\Constants\PackageType;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\PackageParserInterface;
use SuperKernel\Composer\Contract\PackageReconcilerInterface;
use SuperKernel\Composer\Exception\ManifestException;
use SuperKernel\Composer\Exception\PackageCacheException;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\Composer\Metadata\ComposerPackageName;
use SuperKernel\Contract\VendorDirInterface;
use SuperKernel\ScanIsolation\Contract\ScanProcessorInterface;
use SuperKernel\ScanIsolation\Exception\ScanProcessorUnavailableException;

use function is_dir;
use function sprintf;

/**
 * Reconciles one package inside the scan processor and returns its subsequently restored cache instance.
 *
 * @internal
 */
#[Provider(PackageReconcilerInterface::class)]
final class PackageReconciler implements PackageReconcilerInterface
{
    /** @var array<string, array<class-string, non-empty-string>> */
    private array $classes = [];

    /** @var array<string, list<non-empty-string>> */
    private array $autoloadFiles = [];

    private readonly PackageCacheStorage $storage;

    public function __construct(
        private readonly PackageParserInterface $parser,
        private readonly ClassMapBuilder        $builder,
        VendorDirInterface                      $vendorDir,
        private readonly ScanProcessorInterface $scanProcessor,
    )
    {
        $this->storage = new PackageCacheStorage(new PackageCachePath($vendorDir), $vendorDir);
    }

    public function reconcile(ComposerPackageInterface $metadata): PackageInterface
    {
        if (!$this->scanProcessor->support()) {
            throw new ScanProcessorUnavailableException('The supplied package scan processor is unsupported.');
        }

        $name = $metadata->name() ?? throw new ManifestException('Root composer.json must declare a string name.');

        if (!ComposerPackageName::isPackage($name)) {
            throw new PackageCacheException('The package cache requires a normalized Composer package name.');
        }

        $path = $name . '.ser';
        $root = PackageType::Root === $metadata->type();
        $locatedPath = $this->parser->locate($metadata);

        $this->scanProcessor->execute(function () use ($metadata, $name, $path, $root, $locatedPath): void {
            if (!$root && $this->storage->isFile($path)) {
                try {
                    $cached = $this->storage->read($path, $name, $root);

                    if ($cached['package']->reference() === $metadata->reference()) {
                        return;
                    }
                } catch (PackageCacheException) {
                    // An invalid previous package can be replaced only inside a successful scan.
                }
            }

            $package = $this->parser->parse($metadata, $locatedPath);
            $this->storage->write(
                $path,
                $package,
                $this->builder->buildPackage($package, $package->scanRulesFor(AutoloadScope::Development)),
                $this->parser->autoloadFiles($metadata, $locatedPath),
            );
        });

        $restored = $this->storage->read($path, $name, $root);
        $package = $restored['package'];
        $restoredPath = $package->locatedPath();

        if (
            PackageType::Metapackage !== $package->type()
            && $restoredPath !== $locatedPath
            && (null === $restoredPath || !is_dir($restoredPath))
        ) {
            throw new PackageLocationException(sprintf(
                'Package "%s" has no existing directory (cached location: %s).',
                $name,
                $restoredPath ?? 'none',
            ));
        }

        $this->classes[$name] = $restored['classes'];
        $this->autoloadFiles[$name] = $restored['autoloadFiles'];

        return $package;
    }

    public function classes(string $packageName): array
    {
        return $this->classes[$packageName] ?? [];
    }

    public function autoloadFiles(string $packageName): array
    {
        return $this->autoloadFiles[$packageName] ?? [];
    }
}
