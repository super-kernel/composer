<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Model;

use SuperKernel\Composer\Constants\ScanRuleKind;
use SuperKernel\Composer\Constants\ScanRuleSource;
use SuperKernel\Composer\Contract\ScanRuleInterface;

final readonly class ScanRule implements ScanRuleInterface
{
    public function __construct(
        private string         $relativePath,
        private ScanRuleSource $source,
        private ScanRuleKind   $kind,
        private ?string        $namespacePrefix,
    )
    {
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function source(): ScanRuleSource
    {
        return $this->source;
    }

    public function kind(): ScanRuleKind
    {
        return $this->kind;
    }

    public function namespacePrefix(): ?string
    {
        return $this->namespacePrefix;
    }
}
