<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use SuperKernel\Annotation\Factory;
use SuperKernel\Annotation\Provider;
use SuperKernel\Composer\Contract\PackageMetadataRegistryInterface;
use SuperKernel\Composer\PackageMetadataRegistry;

#[
	Provider(PackageMetadataRegistryInterface::class),
	Factory,
]
final class PackageMetadataRegistryFactory
{
	private static PackageMetadataRegistryInterface $packageMetadataRegistry;

	public function __invoke(): PackageMetadataRegistry
	{
		if (!isset(self::$packageMetadataRegistry)) {
			self::$packageMetadataRegistry = new ScannerFactory()()->scan();
		}

		return self::$packageMetadataRegistry;
	}
}