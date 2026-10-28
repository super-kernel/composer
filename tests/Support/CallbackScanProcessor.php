<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Support;

use Closure;
use SuperKernel\ScanIsolation\Contract\ScanProcessorInterface;

/**
 * Executes callbacks synchronously only for deterministic consumer tests.
 */
final class CallbackScanProcessor implements ScanProcessorInterface
{
    public int $calls = 0;

    public int $probes = 0;

    public bool $supported = true;

    public ?Closure $afterScan = null;

    public function support(): bool
    {
        ++$this->probes;

        return $this->supported;
    }

    public function execute(callable $callback): void
    {
        ++$this->calls;
        $callback();

        if (null !== $this->afterScan) {
            ($this->afterScan)();
        }
    }
}
