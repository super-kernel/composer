<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use RuntimeException;
use SuperKernel\Composer\Contract\DriverInterface;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\PackageMetadataInterface;
use SuperKernel\Composer\Contract\PackageMetadataRegistryInterface;
use SuperKernel\Composer\Contract\PackageRegistryInterface;
use SuperKernel\Composer\Contract\ScannerInterface;
use SuperKernel\Composer\Factory\PackageMetadataFactory;
use SuperKernel\PathResolver\Interface\PathResolverInterface;
use function file_exists;
use function file_get_contents;
use function glob;
use function is_dir;
use function str_replace;
use function unserialize;
use const DIRECTORY_SEPARATOR;

final readonly class Scanner implements ScannerInterface
{
	private string $cacheDir;

	public function __construct(
		private DriverInterface          $driver,
		private PathResolverInterface    $pathResolver,
		private PackageRegistryInterface $packageRegistry,
	)
	{
		$cacheDir = $this->pathResolver->resolve('vendor/.skernel/');
		if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
			throw new RuntimeException("Failed to create cache directory: $cacheDir");
		}
		$this->cacheDir = $cacheDir;
	}

	public function scan(): PackageMetadataRegistryInterface
	{
		$this->driver->execute(function () {
			$packages = [];
			foreach ($this->packageRegistry->getPackages() as $package) {
				$this->makePackageMetadata($package);
			}

			$packageMetadataRegistry = new PackageMetadataRegistry(...$packages);
		});

		return $this->loadRegistry();
	}

	private function makePackageMetadata(PackageInterface $package): void
	{
		$packageName = $package->getName();
		$fileName    = str_replace(['/', '\\'], '_', $packageName) . '.metadata';
		$filePath    = $this->cacheDir . DIRECTORY_SEPARATOR . $fileName;

		if (file_exists($filePath)) {
			$content = file_get_contents($filePath);
			if ($content === false) {
				throw new RuntimeException("IO Error: Unable to read metadata file for package '$packageName'.");
			}

			$metadata = unserialize($content);
			if ($metadata instanceof PackageMetadataInterface) {
				if ($metadata->getReference() === $package->getReference()) {
					return;
				}
			}
		}

		$packageMetadata = new PackageMetadataFactory($this->pathResolver)->create($package);

		if (file_put_contents($filePath, serialize($packageMetadata)) === false) {
			throw new RuntimeException("IO Error: Unable to write metadata file for package '$packageName'.");
		}
	}

	private function loadRegistry(): PackageMetadataRegistryInterface
	{
		$storagePath = $this->pathResolver->resolve('vendor/.skernel/');

		if (!is_dir($storagePath)) {
			throw new RuntimeException("Metadata storage directory not found: $storagePath");
		}

		$files = glob($storagePath . '*.metadata');

		$packages = [];
		foreach ($files as $file) {
			$packageMetadata = unserialize(file_get_contents($file));
			if ($packageMetadata instanceof PackageMetadataInterface) {
				$packages[] = $packageMetadata;
			}
		}

		return new PackageMetadataRegistry(...$packages);
	}
}