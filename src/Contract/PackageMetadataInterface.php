<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface PackageMetadataInterface
{
	public function getName(): string;

	public function getType(): string;
}