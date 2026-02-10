<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use RuntimeException;
use SuperKernel\Composer\Contract\PackageSchemaInterface;
use SuperKernel\Composer\Contract\PackageSchemaMetadataInterface;
use SuperKernel\Composer\Schema\DevPackageSchema;
use SuperKernel\Composer\Schema\RootPackageSchema;

final readonly class PackageSchemaMetadata implements PackageSchemaMetadataInterface
{
	/**
	 * @var array<string, PackageSchemaInterface> $packages
	 */
	private array $packages;

	public function __construct(PackageSchemaInterface ...$packages)
	{
		$this->packages = $packages;
	}

	public function getRootPackage(): PackageSchemaInterface
	{
		foreach ($this->packages as $package) {
			if ($package instanceof RootPackageSchema) {
				return $package;
			}
		}
		throw new RuntimeException('The Root package not fount.');
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
		if ($requireDev) {
			return $this->packages;
		}

		$packages = [];
		foreach ($this->packages as $package) {
			if (!($package instanceof DevPackageSchema)) {
				$packages[] = $package;
			}
		}
		return $packages;
	}
}