<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface PackageMetadataRegistryInterface
{
	public function getPackage(string $packageName): PackageMetadataInterface;

	public function hasPackage(string $packageName): bool;

	/**
	 * @return array<PackageMetadataInterface>
	 */
	public function getPackages(): array;
}