<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use SuperKernel\Composer\Contract\PathLocatorInterface;
use SuperKernel\Composer\Package;
use SuperKernel\Composer\PackageMetadataRegistry;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_readable;
use function json_decode;
use function json_validate;

final class PackageMetadataRegistryFactory
{
	public function __invoke(PathLocatorInterface $pathLocator): PackageMetadataRegistry
	{
		$rootPackage = $pathLocator::loadJsonFileToArray($pathLocator->getRootDir());

		$packages = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$pathLocator->getRootDir(),
				FilesystemIterator::SKIP_DOTS
				| FilesystemIterator::CURRENT_AS_FILEINFO
				| FilesystemIterator::FOLLOW_SYMLINKS,
			),
			RecursiveIteratorIterator::SELF_FIRST,
		);

		/* @var SplFileInfo $fileInfo */
		foreach ($iterator as $fileInfo) {
			if (!$fileInfo->isFile() || $fileInfo->getFilename() !== 'composer.json') {
				continue;
			}

			$packages[] = new Package($fileInfo->getPathname());
		}

		return new PackageMetadataRegistry(...$packages);
	}
}