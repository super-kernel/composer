<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\PackageMetadataInterface;

final readonly class PackageRegistry
{
	private array $packages;

	public function __construct(PackageInterface ...$packages)
	{
		foreach ($packages as $package) {
			$this->packages[$package->getName()] = $packages;
		}
	}

	public function hasPackage(string $packageName): bool
	{
		return isset($this->packages[$packageName]);
	}

	public function getPackage(string $packageName): ?PackageMetadataInterface
	{
		return $this->packages[$packageName] ?? null;
	}

	public function getPackages(): array
	{
		return $this->packages;
	}
}