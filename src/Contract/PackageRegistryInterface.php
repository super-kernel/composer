<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Enum\PackageTypeEnum;

interface PackageRegistryInterface
{
	/**
	 * @return array<PackageInterface>
	 */
	public function getPackages(): array;

	public function getPackagesByType(PackageTypeEnum $packageType): array;

	public function getPackage(string $packageName): PackageInterface;

	public function hasPackage(string $packageName): bool;

	public function getRawData(): array;

	public function getLockRawData(): array;
}