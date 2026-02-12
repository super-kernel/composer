<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use RuntimeException;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_readable;
use function json_decode;
use function json_validate;

final class Support
{
	public static function loadComposerFileToArray(string $filePath): array
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