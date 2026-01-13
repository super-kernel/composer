<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Constant\AutoloadType;

interface AutoloadInterface
{
	/**
	 * This method returns the autoload type.
	 *
	 * @return AutoloadType
	 */
	public function type(): AutoloadType;

	/**
	 * This method returns the autoload metadata.
	 *
	 * @return array
	 */
	public function metadata(): array;
}