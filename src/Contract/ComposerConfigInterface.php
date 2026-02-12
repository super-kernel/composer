<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface ComposerConfigInterface
{
	public function getPath(): string;

	public function includeDevRequirements(): bool;

	public function getVendorDir(): string;
}