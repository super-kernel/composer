<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Constant\PackageType;

interface PackageMetaInterface
{
	public function getName(): string;

	public function getVersion(): string;

	public function getReference(): string;

	public function getType(): PackageType;

	public function autoload(): AutoloadInterface;

	public function getExtra(): array;
}