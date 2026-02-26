<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use RuntimeException;
use SuperKernel\Composer\Contract\PackageMetadataInterface;
use SuperKernel\Composer\Contract\PackageMetadataRegistryInterface;
use function sprintf;

final readonly class PackageMetadataRegistry implements PackageMetadataRegistryInterface
{
	/**
	 * @var array<PackageMetadataInterface> $packages
	 */
	private array $packages;

	public function __construct(PackageMetadataInterface ...$packages)
	{
		$packagesArray = [];
		foreach ($packages as $package) {
			$packagesArray[$package->getName()] = $package;
		}
		$this->packages = $packagesArray;
	}

	public function getPackage(string $packageName): PackageMetadataInterface
	{
		if ($this->hasPackage($packageName)) {
			return $this->packages[$packageName];
		}

		throw new RuntimeException(
			sprintf('Package "%s" does not exist.', $packageName),
		);
	}

	public function hasPackage(string $packageName): bool
	{
		return isset($this->packages[$packageName]);
	}

	public function getPackages(): array
	{
		return $this->packages;
	}
}