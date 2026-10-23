<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use SuperKernel\Contract\VendorDirInterface;

final readonly class VendorDir implements VendorDirInterface
{
    /**
     * @param non-empty-string $directory Vendor directory resolved by the factory provider
     */
    public function __construct(private string $directory)
    {
    }

    public function __toString(): string
    {
        return $this->directory;
    }
}
