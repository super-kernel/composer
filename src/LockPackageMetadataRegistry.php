<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\LockPackageMetadataRegistryInterface;
use function array_merge;

final readonly class LockPackageMetadataRegistry implements LockPackageMetadataRegistryInterface
{
	private array $rowData;

	private array $packages;

	private array $devPackages;

	public function __construct(array $rowData)
	{
		$this->rowData     = $rowData;
		$this->packages    = self::loadPackages($rowData['packages'] ?? []);
		$this->devPackages = self::loadPackages($rowData['packages-dev'] ?? []);
	}

	public function hasPackage(string $packageName): bool
	{
		return isset($this->packages[$packageName])
		       || isset($this->devPackages[$packageName]);
	}

	public function isDevPackage(string $packageName): bool
	{
		return isset($this->devPackages[$packageName]);
	}

	public function getAllPackages(bool $requireDev = true): array
	{
		if ($requireDev) {
			return array_merge($this->devPackages, $this->packages);
		}
		return $this->packages;
	}

	public function getRawData(): array
	{
		return $this->rowData;
	}

	private static function loadPackages(array $packages): array
	{
		$lockPackages = [];
		foreach ($packages as $package) {
			$lockPackages[$package['name']] = $package;
		}
		return $lockPackages;
	}
}