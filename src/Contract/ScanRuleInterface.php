<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Constants\ScanRuleKind;
use SuperKernel\Composer\Constants\ScanRuleSource;

/**
 * Describes one safe package-root-relative autoload scan inclusion.
 */
interface ScanRuleInterface
{
    /**
     * @return string Normalized package-root-relative path using `/` separators
     */
    public function relativePath(): string;

    /**
     * Returns the Composer autoload declaration that produced the rule.
     */
    public function source(): ScanRuleSource;

    /**
     * Returns the filesystem shape expected at the rule path.
     */
    public function kind(): ScanRuleKind;

    /**
     * @return string|null PSR namespace or class prefix, or null for classmap rules
     */
    public function namespacePrefix(): ?string;
}
