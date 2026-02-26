<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use Generator;
use RuntimeException;
use SuperKernel\Composer\Contract\ScannerInterface;
use SuperKernel\Composer\Driver\PcntlDriver;
use SuperKernel\Composer\Scanner;
use SuperKernel\PathResolver\Factory\PathResolverFactory;

final class ScannerFactory
{
	private static ScannerInterface $scanner;

	public function __invoke(): ScannerInterface
	{
		if (!isset(self::$scanner)) {
			self::$scanner = $this->makeScanner();
		}

		return self::$scanner;
	}

	private function makeScanner(): ScannerInterface
	{
		foreach ($this->getDrivers() as $driver) {
			if ($driver->supports()) {
				$pathResolver    = new PathResolverFactory()();
				$packageRegistry = new PackageRegistryFactory()($pathResolver);

				return new Scanner($driver, $pathResolver, $packageRegistry);
			}
		}

		throw new RuntimeException('No process driver available for metadata scanning.');
	}

	private function getDrivers(): Generator
	{
		yield new PcntlDriver();
	}
}