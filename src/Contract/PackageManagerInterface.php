<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface PackageManagerInterface
{
	/**
	 * @return array<PackageInterface>
	 */
	public function getPackages(): array;
}