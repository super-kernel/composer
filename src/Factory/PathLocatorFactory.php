<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use InvalidArgumentException;
use RuntimeException;
use SuperKernel\Composer\Contract\PathLocatorInterface;
use SuperKernel\Composer\PathLocator;
use function getcwd;
use function is_dir;
use function is_null;
use function rtrim;
use function trigger_error;
use const DIRECTORY_SEPARATOR;
use const E_USER_WARNING;

final class PathLocatorFactory
{
	private static PathLocatorInterface $pathLocator;

	public function __invoke(?string $rootDir = null): PathLocatorInterface
	{
		if (!isset(self::$pathLocator)) {
			self::$pathLocator = new PathLocator($this->getRootDir($rootDir));
		}

		return self::$pathLocator;
	}

	private function getRootDir(?string $rootDir = null): string
	{
		if (!is_null($rootDir)) {
			$rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);

			if (!is_dir($rootDir)) {
				throw new InvalidArgumentException("Root directory does not exist: $rootDir");
			}

			return $rootDir;
		}

		trigger_error(
			message    : 'The root directory is not set; the current working directory will be used as the root directory.',
			error_level: E_USER_WARNING,
		);

		$rootDir = getcwd();

		if (false === $rootDir) {
			throw new RuntimeException('Unable to obtain the current working directory, please configure the root directory.',);
		}

		return $rootDir;
	}
}