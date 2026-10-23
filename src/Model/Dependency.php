<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Model;

use SuperKernel\Composer\Contract\DependencyInterface;

final readonly class Dependency implements DependencyInterface
{
    public function __construct(private string $name, private string $constraint, private bool $devRequirement)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function constraint(): string
    {
        return $this->constraint;
    }

    public function isDevRequirement(): bool
    {
        return $this->devRequirement;
    }
}
