<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

final readonly class PathLocator
{
	public function __construct(public string $rootDir, public string $vendorDir)
	{
	}
}