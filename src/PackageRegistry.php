<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use RuntimeException;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\PackageRegistryInterface;
use SuperKernel\Composer\Enum\PackageTypeEnum;

final readonly class PackageRegistry implements PackageRegistryInterface
{
	/**
	 * @var array<PackageInterface> $packages
	 */
	private array $packages;

	public function __construct(private array $jsonRawData, private array $lockRawData, PackageInterface ...$packages)
	{
		$packagesArray = [];
		foreach ($packages as $package) {
			$packagesArray[$package->getName()] = $package;
		}
		$this->packages = $packagesArray;
	}

	public function getPackages(): array
	{
		return $this->packages;
	}

	public function getPackagesByType(PackageTypeEnum $packageType): array
	{
		$packages = [];
		foreach ($this->packages as $package) {
			if ($packageType->value === $package->getType()) {
				$packages[] = $package;
			}
		}
		return $packages;
	}

	public function getPackage(string $packageName): PackageInterface
	{
		if ($this->hasPackage($packageName)) {
			return $this->packages[$packageName];
		}

		throw new RuntimeException("Package '$packageName' not found");
	}

	public function hasPackage(string $packageName): bool
	{
		return isset($this->packages[$packageName]);
	}

	public function getRawData(): array
	{
		return $this->jsonRawData;
	}

	public function getLockRawData(): array
	{
		return $this->lockRawData;
	}
}