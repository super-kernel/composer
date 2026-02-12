<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use SuperKernel\Annotation\Factory;
use SuperKernel\Annotation\Provider;
use SuperKernel\Composer\Contract\ComposerConfigInterface;
use SuperKernel\Composer\Contract\ScanDriverInterface;
use SuperKernel\Composer\Contract\PackageMetadataInterface;
use SuperKernel\Composer\PackageMetadata;
use SuperKernel\Composer\Scan\ScanHandler;

#[
	Provider(PackageMetadataInterface::class),
	Factory,
]
final class PackageMetadataFactory
{
	private static PackageMetadataInterface $packageMetadata;

	public function __invoke(
		?ComposerConfigInterface $composerConfig = null,
		?ScanDriverInterface     $scanDriver = null,
	): PackageMetadata
	{
		if (!isset(self::$packageMetadata)) {
			$scanHandler = new ScanHandler(
				new PackageSchemaMetadataFactory()(
					$composerConfig,
				),
				$scanDriver ?? new ScanDriverFactory()(),
			);

			self::$packageMetadata = $scanHandler->scan();
		}
		return self::$packageMetadata;
	}
}