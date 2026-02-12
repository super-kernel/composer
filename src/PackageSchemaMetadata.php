<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\ComposerConfigInterface;
use SuperKernel\Composer\Contract\PackageSchemaInterface;
use SuperKernel\Composer\Contract\PackageSchemaMetadataInterface;
use function array_merge;

final readonly class PackageSchemaMetadata implements PackageSchemaMetadataInterface
{
	/**
	 * @var array<string, PackageSchemaInterface> $packages
	 */
	private array $packages;

	public function __construct(private ComposerConfigInterface $composerConfig, PackageSchemaInterface ...$packages)
	{
		$this->packages = $packages;
	}

	public function getRootPackage(): PackageSchemaInterface
	{
		return $this->composerConfig->getRootPackageSchema();
	}

	public function getPackage(string $packageName): ?PackageSchemaInterface
	{
		return $this->packages[$packageName] ?? null;
	}

	public function hasPackage(string $packageName): bool
	{
		return isset($this->packages[$packageName])
		       || isset($this->devPackages[$packageName]);
	}

	public function getAllPackages(bool $requireDev = true): array
	{
		$packages = [
			$this->composerConfig->getRootPackageSchema(),
		];

		if ($requireDev) {
			return array_merge($this->packages, $packages);
		}

		foreach ($this->packages as $package) {
			if ($package->isDevRequirement()) {
				continue;
			}
			$packages[] = $package;
		}

		return $packages;
	}
}