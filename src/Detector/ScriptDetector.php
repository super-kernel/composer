<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Detector;

use SuperKernel\Composer\Contract\DetectorInterface;
use SuperKernel\Composer\Path\FilesystemPath;

use function getcwd;
use function is_file;
use function is_string;

final readonly class ScriptDetector implements DetectorInterface
{
    private ?string $directory;

    public function __construct()
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? null;
        $directory = is_string($script) && is_file($script) ? getcwd() : false;

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
