<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface ScannerInterface
{
	public function scan(): PackageMetadataRegistryInterface;
}