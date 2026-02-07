<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use InvalidArgumentException;
use RuntimeException;
use SuperKernel\Annotation\Factory;
use SuperKernel\Annotation\Provider;
use SuperKernel\Composer\ComposerConfig;
use SuperKernel\Composer\Contract\ComposerConfigInterface;
use function getcwd;
use function is_dir;
use function is_null;
use function rtrim;
use function trigger_error;
use const DIRECTORY_SEPARATOR;
use const E_USER_WARNING;

#[
	Provider(ComposerConfigInterface::class),
	Factory,
]
final class ComposerConfigFactory
{
	private static ComposerConfigInterface $composerConfig;

	public function __invoke(?string $path = null): ComposerConfigInterface
	{
		if (!isset(self::$composerConfig)) {
			self::$composerConfig = new ComposerConfig($this->getRootDir($path));
		}
		return self::$composerConfig;
	}

	private function getRootDir(?string $path = null): string
	{
		if (!is_null($path)) {
			$path = rtrim($path, DIRECTORY_SEPARATOR);

			if (!is_dir($path)) {
				throw new InvalidArgumentException("Root directory does not exist: $path");
			}

			return $path;
		}

		trigger_error(
			message    : 'The root directory is not set; the current working directory will be used as the root directory.',
			error_level: E_USER_WARNING,
		);

		$path = getcwd();

		if (false === $path) {
			throw new RuntimeException('Unable to obtain the current working directory, please configure the root directory.',);
		}

		return $path;
	}
}