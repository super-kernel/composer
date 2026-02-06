<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Provider;

use SuperKernel\Annotation\Factory;
use SuperKernel\Annotation\Provider;
use SuperKernel\Composer\Contract\PackageManagerInterface;
use SuperKernel\Composer\PathLocator;

#[
	Provider(PackageManagerInterface::class),
	Factory,
]
final class PackageManagerProvider
{
	public function __invoke(PathLocator $pathLocator): array
	{
	}
}