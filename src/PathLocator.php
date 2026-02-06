<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use RuntimeException;
use SuperKernel\Composer\Contract\PathLocatorInterface;
use function getcwd;
use function is_dir;
use function is_null;

final readonly class PathLocator implements PathLocatorInterface
{
	public function __construct(private string $rootDir)
	{
	}

	public function getRootDir(): string
	{
		return $this->rootDir;
	}

	public function getCacheDir(): string
	{
	}

	public function getRootPackage(): string
	{
		// TODO: Implement getRootPackage() method.
	}

	public static function loadJsonFileToArray(string $filepath): array
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