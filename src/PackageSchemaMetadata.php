<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\PackageSchemaInterface;
use SuperKernel\Composer\Contract\PackageSchemaMetadataInterface;
use function array_merge;

final readonly class PackageSchemaMetadata implements PackageSchemaMetadataInterface
{
	private PackageSchemaInterface $rootPackage;

	/**
	 * @var array<string, PackageSchemaInterface> $packages
	 */
	private array $packages;

	/**
	 * @var array<string, PackageSchemaInterface> $devPackages
	 */
	private array $devPackages;

	public function __construct(private array $rowData, array $rootPackage)
	{
		$this->rootPackage = new PackageSchema($rootPackage);

		$this->packages    = $this->buildPackageMap($this->rowData['packages']);
		$this->devPackages = $this->buildPackageMap($this->rowData['packages-dev']);
	}

	private function buildPackageMap(array $rows): array
	{
		$map = [];
		foreach ($rows as $packageData) {
			$package = new PackageSchema($packageData);

			$map[$package->getName()] = $package;
		}
		return $map;
	}

	public function getRootPackage(): PackageSchemaInterface
	{
		return $this->rootPackage;
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
			return array_merge($this->packages, $this->devPackages);
		}
		return $this->packages;
	}

	public function getRawData(): array
	{
		return $this->rowData;
	}
}