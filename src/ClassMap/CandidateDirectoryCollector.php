<?php

declare(strict_types=1);

namespace SuperKernel\Composer\ClassMap;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SuperKernel\Composer\Contract\ScanRuleInterface;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;
use SuperKernel\Composer\Exception\ClassMapScanException;
use SuperKernel\Composer\Path\FilesystemPath;
use Throwable;

use function sprintf;

/**
 * Records every candidate file below an existing package-contained directory for one scan rule.
 *
 * @internal
 */
final class CandidateDirectoryCollector
{
    /**
     * @param array<string, list<ScanRuleInterface>> $files Candidate files and their matching rules, updated in place
     *
     * @throws ClassMapScanException When the directory resolves outside the package root or cannot be scanned
     */
    public static function collect(
        string               $directory,
        string               $packageRoot,
        ScanRuleSetInterface $scanRules,
        ScanRuleInterface    $rule,
        array               &$files,
    ): void
    {
        $canonicalDirectory = FilesystemPath::canonicalDirectory($directory);

        if (null === $canonicalDirectory) {
            return;
        }

        if (!FilesystemPath::isWithin($canonicalDirectory, $packageRoot)) {
            throw new ClassMapScanException(sprintf(
                'Class scan directory %s is outside package root %s.',
                $canonicalDirectory,
                $packageRoot,
            ));
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($canonicalDirectory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $entry) {
                if ($entry->isFile()) {
                    CandidateFileCollector::collect($entry->getPathname(), $packageRoot, $scanRules, $rule, $files);
                }
            }
        } catch (Throwable $exception) {
            throw new ClassMapScanException(
                sprintf('Unable to scan class directory %s.', $canonicalDirectory),
                previous: $exception,
            );
        }
    }
}
