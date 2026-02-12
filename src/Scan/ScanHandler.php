<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Scan;

use SuperKernel\Composer\Contract\ScanDriverInterface;
use SuperKernel\Composer\Contract\PackageMetadataInterface;
use SuperKernel\Composer\Contract\PackageSchemaMetadataInterface;

final readonly class ScanHandler
{
	public function __construct(
		private PackageSchemaMetadataInterface $packageSchemaMetadata,
		private ScanDriverInterface            $scanDriver,
	)
	{
	}

	public function scan(): PackageMetadataInterface
	{
		$scanned = $this->scanDriver->scan();
		if ($scanned->isScanned()) {

		}
	}
}