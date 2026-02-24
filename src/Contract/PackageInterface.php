<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface PackageInterface
{
	public function getName(): string;

	public function getType(): string;
}
