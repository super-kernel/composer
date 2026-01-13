<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Constant\PackageType;
use SuperKernel\Composer\Contract\PackageCollectorInterface;
use SuperKernel\Composer\Contract\PackageMetaInterface;

final readonly class PackageCollector implements PackageCollectorInterface
{
	private array $packages;

	public function __construct(PackageMetaInterface ...$packages)
	{
		foreach ($packages as $package) {
			$this->packages[] = $package;
		}
	}

	public function getPackages(): array
	{
		// TODO: Implement getPackages() method.
	}

	public function getPackagesByType(PackageType $type): array
	{
		// TODO: Implement getPackagesByType() method.
	}
}