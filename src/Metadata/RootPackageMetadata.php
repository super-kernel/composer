<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use SuperKernel\Composer\Contract\PackageMetadataInterface;

final class RootPackageMetadata implements PackageMetadataInterface
{
	public function __construct(string $vendorDir)
	{
	}

	public function getPackages(): array
	{
		// TODO: Implement getPackages() method.
	}
}