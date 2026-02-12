<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface ScanDriverInterface
{
	public function scan(): ScannedInterface;
}