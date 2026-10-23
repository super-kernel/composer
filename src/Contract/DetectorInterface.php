<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface DetectorInterface
{
    /**
     * @return bool Whether this detector identified a project root in its captured execution environment
     */
    public function support(): bool;

    /**
     * Use support() to determine whether this directory can be used as the project root.
     *
     * @return string Detected directory, including phar:// paths, or an empty string when unsupported
     */
    public function projectDirectory(): string;
}
