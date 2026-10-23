<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Detector;

use SuperKernel\Composer\Contract\DetectorInterface;
use SuperKernel\Composer\Path\FilesystemPath;

use function getcwd;

final readonly class WorkingDirectoryDetector implements DetectorInterface
{
    private ?string $directory;

    public function __construct()
    {
        $directory = getcwd();

        $this->directory = FilesystemPath::findProjectDirectory(false === $directory ? null : $directory);
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
