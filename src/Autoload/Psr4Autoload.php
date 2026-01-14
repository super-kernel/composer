<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Autoload;

use SuperKernel\Composer\Constant\AutoloadType;
use SuperKernel\Composer\Contract\AutoloadInterface;

final class Psr4Autoload implements AutoloadInterface
{
	public function type(): AutoloadType
	{
		// TODO: Implement type() method.
	}

	public function metadata(): array
	{
		// TODO: Implement metadata() method.
	}
}