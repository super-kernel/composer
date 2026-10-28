<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Detector;

use SuperKernel\Composer\Contract\DetectorInterface;
use SuperKernel\Composer\Path\FilesystemPath;

use function getcwd;
use function getenv;

final readonly class ComposerScriptDetector implements DetectorInterface
{
    private ?string $directory;

    public function __construct()
    {
        $mode = getenv('COMPOSER_DEV_MODE');
        $directory = '0' === $mode || '1' === $mode ? getcwd() : false;

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
