<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Detector;

use Phar;
use SuperKernel\Composer\Contract\DetectorInterface;

use function class_exists;

/**
 * Identifies application execution from a Phar archive and supplies that archive's internal root directory.
 */
final readonly class NullDetector implements DetectorInterface
{
    private ?string $directory;

    public function __construct()
    {
        $archive = class_exists(Phar::class, false) ? Phar::running() : '';

        $this->directory = '' === $archive ? null : $archive;
    }

    public function support(): bool
    {
        return null !== $this->directory;
    }

    public function projectDirectory(): string
    {
        return $this->directory ?? '';
    }
}
