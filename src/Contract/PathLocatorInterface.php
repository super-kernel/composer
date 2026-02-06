<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface PathLocatorInterface
{
	public function getRootDir(): string;

	public function getRootPackage(): string;

	public static function loadJsonFileToArray(string $filepath): array;
}