<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

/**
 * Groups deterministic scan inclusions and Composer classmap exclusions.
 */
interface ScanRuleSetInterface
{
    /**
     * @return list<ScanRuleInterface> Deduplicated inclusions in deterministic source/prefix/path/kind order
     */
    public function inclusions(): array;

    /**
     * @return list<string> Deduplicated Composer package-root-relative exclusion patterns in lexical order
     */
    public function exclusions(): array;

    /**
     * Tests a package-root-relative path against Composer classmap exclusions.
     *
     * Unsafe paths containing a null byte or parent traversal never match.
     */
    public function isExcluded(string $packageRelativePath): bool;
}
