<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use RuntimeException;
use SuperKernel\Annotation\Factory;
use SuperKernel\Annotation\Provider;
use SuperKernel\Composer\Contract\PackageRegistryInterface;
use SuperKernel\Composer\Package;
use SuperKernel\Composer\PackageRegistry;
use SuperKernel\PathResolver\Factory\PathResolverFactory;
use SuperKernel\PathResolver\Interface\PathResolverInterface;

#[
	Provider(PackageRegistryInterface::class),
	Factory,
]
final class PackageRegistryFactory
{
	private static PackageRegistry $packageRegistry;

	public function __invoke(?PathResolverInterface $pathResolver = null): PackageRegistry
	{
		if (!isset(self::$packageRegistry)) {
			$pathResolver ??= new PathResolverFactory()();

			$jsonRawData = $this->loadComposerFileToArray($pathResolver->resolve('composer.json'));
			$lockRawData = $this->loadComposerFileToArray($pathResolver->resolve('composer.lock'));

			$packages = [new Package($jsonRawData, true)];
			foreach ([
				         ...$lockRawData['packages'],
				         ...$lockRawData['packages-dev'],
			         ] as $data) {
				$packages[] = new Package($data);
			}

			self::$packageRegistry = new PackageRegistry($jsonRawData, $lockRawData, ...$packages);
		}

		return self::$packageRegistry;
	}

	private function loadComposerFileToArray(string $filePath): array
	{
		if (!is_file($filePath)) {
			throw new RuntimeException("File not found or not a regular file: $filePath");
		}

		if (!is_readable($filePath)) {
			throw new RuntimeException("File is not readable: $filePath");
		}

		$fileContents = file_get_contents($filePath);

		if (false === $fileContents) {
			throw new RuntimeException("Failed to read file: $filePath");
		}

		if (!json_validate($fileContents)) {
			throw new RuntimeException("Invalid JSON in file: $filePath");
		}

		$data = json_decode($fileContents, true);

		if (!is_array($data)) {
			throw new RuntimeException("Unexpected JSON root type in file: $filePath");
		}

		return $data;
	}
}