<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use SuperKernel\Annotation\Factory;
use SuperKernel\Annotation\Provider;
use SuperKernel\Composer\ComposerConfig;
use SuperKernel\Composer\Contract\PackageSchemaMetadataInterface;
use SuperKernel\Composer\PackageSchema;
use SuperKernel\Composer\PackageSchemaMetadata;
use SuperKernel\Composer\Support;

#[
	Provider(PackageSchemaMetadataInterface::class),
	Factory,
]
final class PackageSchemaMetadataFactory
{
	private PackageSchemaMetadataInterface $lockPackageMetadata;

	public function __invoke(?ComposerConfig $composerConfig = null): PackageSchemaMetadataInterface
	{
		if (!isset($this->lockPackageMetadata)) {
			$composerConfig ??= new ComposerConfigFactory()();

			$packages = [
				$composerConfig->getRootPackageSchema(),
			];

			$lockPackageData = Support::loadComposerFileToArray($composerConfig->getPath() . '/composer.lock');
			foreach ($lockPackageData['packages-dev'] as $packageData) {
				$packages[] = new PackageSchema($packageData, true);
			}
			foreach ($lockPackageData['packages'] as $packageData) {
				$packages[] = new PackageSchema($packageData, false);
			}

			$this->lockPackageMetadata = new PackageSchemaMetadata($composerConfig, ...$packages);
		}
		return $this->lockPackageMetadata;
	}
}