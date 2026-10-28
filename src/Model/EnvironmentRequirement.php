<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Model;

use SuperKernel\Contract\EnvironmentRequirementInterface;

final readonly class EnvironmentRequirement implements EnvironmentRequirementInterface
{
    public function __construct(
        private string $name,
        private string $constraint,
        private string $requiredBy,
        private bool   $devRequirement,
    )
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

    public function requiredBy(): string
    {
        return $this->requiredBy;
    }

    public function isDevRequirement(): bool
    {
        return $this->devRequirement;
    }
}
