<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use RuntimeException;
use SuperKernel\Annotation\Factory;
use SuperKernel\Annotation\Provider;
use SuperKernel\Composer\ComposerConfig;
use SuperKernel\Composer\Contract\PackageSchemaMetadataInterface;
use SuperKernel\Composer\PackageSchemaMetadata;

#[
	Provider(PackageSchemaMetadataInterface::class),
	Factory,
]
final class PackageSchemaMetadataFactory
{
	private PackageSchemaMetadataInterface $lockPackageMetadata;

	public function __invoke(ComposerConfig $composerConfig): PackageSchemaMetadataInterface
	{
		if (!isset($this->lockPackageMetadata)) {
			$rootPackageData = $this->loadJsonFileToArray($composerConfig->getPath() . '/composer.json');
			$lockPackageData = $this->loadJsonFileToArray($composerConfig->getPath() . '/composer.lock');

			$this->lockPackageMetadata = new PackageSchemaMetadata($lockPackageData, $rootPackageData);
		}
		return $this->lockPackageMetadata;
	}

	private static function loadJsonFileToArray(string $filepath): array
	{
		if (!is_file($filepath)) {
			throw new RuntimeException("File not found or not a regular file: $filepath");
		}

		if (!is_readable($filepath)) {
			throw new RuntimeException("File is not readable: $filepath");
		}

		$fileContents = file_get_contents($filepath);

		if (false === $fileContents) {
			throw new RuntimeException("Failed to read file: $filepath");
		}

		if (!json_validate($fileContents)) {
			throw new RuntimeException("Invalid JSON in file: $filepath");
		}

		$data = json_decode($fileContents, true);

		if (!is_array($data)) {
			throw new RuntimeException("Unexpected JSON root type in file: $filepath");
		}

		return $data;
	}
}