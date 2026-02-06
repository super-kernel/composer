<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use SuperKernel\Composer\Contract\LockPackageMetadataRegistryInterface;
use SuperKernel\Composer\Contract\PathLocatorInterface;
use SuperKernel\Composer\LockPackageMetadataRegistry;
use const DIRECTORY_SEPARATOR;

final class LockPackageMetadataRegistryFactory
{
	private LockPackageMetadataRegistryInterface $lockPackageMetadataRegistry;

	public function __invoke(PathLocatorInterface $pathLocator): LockPackageMetadataRegistryInterface
	{
		if (!isset($this->lockPackageMetadataRegistry)) {
			$lockData = $pathLocator::loadJsonFileToArray($pathLocator->getRootDir() . DIRECTORY_SEPARATOR . 'composer.lock');

			$this->lockPackageMetadataRegistry = new LockPackageMetadataRegistry($lockData);
		}
		return $this->lockPackageMetadataRegistry;
	}
}