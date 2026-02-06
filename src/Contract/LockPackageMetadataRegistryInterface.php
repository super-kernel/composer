<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface LockPackageMetadataRegistryInterface
{
	public function hasPackage(string $packageName): bool;

	public function isDevPackage(string $packageName): bool;

	public function getAllPackages(bool $requireDev = true): array;

	public function getRawData(): array;
}