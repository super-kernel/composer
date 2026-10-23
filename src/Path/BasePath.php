<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use SuperKernel\Contract\BasePathInterface;

final readonly class BasePath implements BasePathInterface
{
    /**
     * @param non-empty-string $directory Canonical directory supplied by the base path provider
     */
    public function __construct(private string $directory)
    {
    }

    public function __toString(): string
    {
        return $this->directory;
    }
}
