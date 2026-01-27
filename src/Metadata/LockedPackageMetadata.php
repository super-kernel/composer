<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use SuperKernel\Composer\Contract\PackageMetadataInterface;

final class LockedPackageMetadata implements PackageMetadataInterface
{
	private function __construct(string $vendorDir)
	{
	}

	public function getPackages(): array
	{
		// TODO: Implement getPackages() method.
	}
}