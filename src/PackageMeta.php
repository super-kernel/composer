<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Constant\AutoloadType;
use SuperKernel\Composer\Constant\PackageType;
use SuperKernel\Composer\Contract\PackageMetaInterface;

final class PackageMeta implements PackageMetaInterface
{
	public function __construct()
	{
	}

	public function getName(): string
	{
		// TODO: Implement getName() method.
	}

	public function getVersion(): string
	{
		// TODO: Implement getVersion() method.
	}

	public function getReference(): string
	{
		// TODO: Implement getReference() method.
	}

	public function getType(): PackageType
	{
		// TODO: Implement getType() method.
	}

	public function autoload(): AutoloadType
	{
		// TODO: Implement autoload() method.
	}

	public function getExtra(): array
	{
		// TODO: Implement getExtra() method.
	}
}