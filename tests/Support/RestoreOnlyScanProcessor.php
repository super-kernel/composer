<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Support;

use SuperKernel\ScanIsolation\Contract\ScanProcessorInterface;

/**
 * Lets consumer tests restore prebuilt package artifacts without a Phar runtime.
 */
final class RestoreOnlyScanProcessor implements ScanProcessorInterface
{
    public function support(): bool
    {
        return true;
    }

    public function execute(callable $callback): void
    {
    }
}
