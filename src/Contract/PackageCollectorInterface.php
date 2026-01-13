<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Constant\PackageType;

interface PackageCollectorInterface
{
	/**
	 * This method returns all packages.
	 *
	 * @return array<PackageMetaInterface>
	 */
	public function getPackages(): array;

	/**
	 * This method returns all packages of a specific type.
	 *
	 * @param PackageType $type
	 *
	 * @return array<PackageMetaInterface>
	 */
	public function getPackagesByType(PackageType $type): array;
}