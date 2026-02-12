<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Driver;

use SuperKernel\Composer\Contract\ScanDriverInterface;
use SuperKernel\Composer\Contract\ScannedInterface;

final readonly class PcntlScanDriver implements ScanDriverInterface
{
	public function scan(): ScannedInterface
	{
	}
}