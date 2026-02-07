<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use SuperKernel\Composer\ComposerConfig;
use SuperKernel\Composer\Contract\PackageMetadataInterface;
use SuperKernel\Composer\PackageMetadata;

final class PackageMetadataFactory
{
	private static PackageMetadataInterface $packageMetadata;

	public function __invoke(?ComposerConfig $composerConfig = null): PackageMetadata
	{
		if (!isset(self::$packageMetadata)) {
			$composerConfig ??= new ComposerConfigFactory()();

			self::$packageMetadata = new PackageMetadata(
				new PackageSchemaMetadataFactory()($composerConfig),
			);
		}

		return self::$packageMetadata;
	}
}