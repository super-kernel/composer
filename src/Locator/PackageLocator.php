<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Locator;

use SuperKernel\Attribute\Provider;
use SuperKernel\Composer\Contract\PackageLocatorInterface;

use function rtrim;

#[Provider(PackageLocatorInterface::class)]
final readonly class PackageLocator implements PackageLocatorInterface
{
    public function locate(string $packageName, string $composerType, string $vendorDirectory): string
    {
        return rtrim($vendorDirectory, '/') . '/' . $packageName;
    }
}
