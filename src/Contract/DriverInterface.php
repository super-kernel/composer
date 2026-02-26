<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface DriverInterface
{
	public function supports(): bool;

	public function execute(callable $task): void;
}