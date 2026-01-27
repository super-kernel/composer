<?php
declare(strict_types=1);

namespace SuperKernel\Composer\FileReader;

use RuntimeException;

final class JsonFileReader
{
	/**
	 * @param string $path
	 *
	 * @return array
	 *
	 * @deprecated The encapsulation of this method will be replaced by the `super-kernel/filesystem` component.
	 */
	public static function load(string $path): array
	{
		if (!is_file($path)) {
			throw new RuntimeException("File not found or not a regular file: $path");
		}

		if (!is_readable($path)) {
			throw new RuntimeException("File is not readable: $path");
		}

		$fileContents = file_get_contents($path);

		if (false === $fileContents) {
			throw new RuntimeException("Failed to read file: $path");
		}

		if (!json_validate($fileContents)) {
			throw new RuntimeException("Invalid JSON in file: $path");
		}

		$data = json_decode($fileContents, true);

		if (!is_array($data)) {
			throw new RuntimeException("Unexpected JSON root type in file: $path");
		}

		return $data;
	}
}