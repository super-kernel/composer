<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\PackageMetadataInterface;

final readonly class PackageMetadataRegistry
{
	private array $packages;

	public function __construct(PackageMetadataInterface ...$packageMetadataList)
	{
		foreach ($packageMetadataList as $packageMetadata) {
			$this->packages[$packageMetadata->getName()] = $packageMetadata;
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