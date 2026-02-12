<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use SuperKernel\Composer\Contract\ScanDriverInterface;
use SuperKernel\Composer\Driver\PcntlScanDriver;

final class ScanDriverFactory
{
	private static ?PcntlScanDriver $pcntlScanDriver = null;

	public function __invoke(): ScanDriverInterface
	{
		return match (true) {
			default => self::$pcntlScanDriver ??= new PcntlScanDriver(),
		};
	}
}