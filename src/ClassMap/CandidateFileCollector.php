<?php

declare(strict_types=1);

namespace SuperKernel\Composer\ClassMap;

use SuperKernel\Composer\Contract\ScanRuleInterface;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;
use SuperKernel\Composer\Exception\ClassMapScanException;
use SuperKernel\Composer\Path\FilesystemPath;

use function is_file;
use function is_string;
use function ltrim;
use function realpath;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

/**
 * Records an existing package-contained candidate file with its scan rule unless the rule set excludes it.
 *
 * @internal
 */
final class CandidateFileCollector
{
    /**
     * @param array<string, list<ScanRuleInterface>> $files Candidate files and their matching rules, updated in place
     *
     * @throws ClassMapScanException When the file resolves outside the package root or to the root itself
     */
    public static function collect(
        string               $file,
        string               $packageRoot,
        ScanRuleSetInterface $scanRules,
        ScanRuleInterface    $rule,
        array               &$files,
    ): void
    {
        $canonicalFile = realpath($file);

        if (!is_string($canonicalFile) || !is_file($canonicalFile)) {
            return;
        }

        $canonicalFile = str_replace('\\', '/', $canonicalFile);

        if (!FilesystemPath::isWithin($canonicalFile, $packageRoot) || $canonicalFile === $packageRoot) {
            throw new ClassMapScanException(sprintf(
                'Class scan file %s is outside package root %s.',
                $canonicalFile,
                $packageRoot,
            ));
        }

        $relativePath = ltrim(substr($canonicalFile, strlen($packageRoot)), '/');

        if ($scanRules->isExcluded($relativePath)) {
            return;
        }

        $files[$canonicalFile][] = $rule;
    }
}
