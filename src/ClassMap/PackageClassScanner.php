<?php

declare(strict_types=1);

namespace SuperKernel\Composer\ClassMap;

use SuperKernel\Composer\Constants\ScanRuleKind;
use SuperKernel\Composer\Constants\ScanRuleSource;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;
use SuperKernel\Composer\Exception\ClassMapScanException;
use SuperKernel\Composer\Path\FilesystemPath;
use SuperKernel\Tokenizer\Contract\ClassNameParserInterface;

use function array_keys;
use function array_map;
use function file_get_contents;
use function glob;
use function is_dir;
use function is_file;
use function ksort;
use function sort;
use function sprintf;
use function str_starts_with;

use const GLOB_NOSORT;
use const SORT_STRING;

/**
 * Derives one package's sorted class map from its validated scan rules.
 *
 * @internal
 */
final readonly class PackageClassScanner
{
    public function __construct(private ClassNameParserInterface $classNameParser)
    {
    }

    /**
     * Explicit classmap rules outrank PSR-4 directories, which outrank PSR-0 directories.
     *
     * @param ScanRuleSetInterface|null $scanRules Rules to apply, or null for the package's production rules
     *
     * @return array<class-string, non-empty-string> Sorted class map
     *
     * @throws ClassMapScanException When a candidate resolves outside the package root or cannot be scanned
     */
    public function scan(PackageInterface $package, ?ScanRuleSetInterface $scanRules = null): array
    {
        $packageRoot = FilesystemPath::canonicalDirectory($package->locatedPath());

        if (null === $packageRoot) {
            return [];
        }

        $scanRules ??= $package->scanRules();
        $files = [];

        foreach ($scanRules->inclusions() as $rule) {
            $candidate = $packageRoot . '/' . $rule->relativePath();

            switch ($rule->kind()) {
                case ScanRuleKind::File:
                    CandidateFileCollector::collect($candidate, $packageRoot, $scanRules, $rule, $files);
                    break;

                case ScanRuleKind::Directory:
                    CandidateDirectoryCollector::collect($candidate, $packageRoot, $scanRules, $rule, $files);
                    break;

                case ScanRuleKind::Glob:
                    $matches = glob($candidate, GLOB_NOSORT);

                    if (false === $matches) {
                        throw new ClassMapScanException(sprintf('Unable to expand classmap pattern %s.', $candidate));
                    }

                    sort($matches, SORT_STRING);

                    foreach ($matches as $match) {
                        if (is_dir($match)) {
                            CandidateDirectoryCollector::collect($match, $packageRoot, $scanRules, $rule, $files);
                        } elseif (is_file($match)) {
                            CandidateFileCollector::collect($match, $packageRoot, $scanRules, $rule, $files);
                        }
                    }

                    break;
            }
        }

        $filePaths = array_keys($files);
        sort($filePaths, SORT_STRING);
        $selectedClasses = [];

        foreach ($filePaths as $file) {
            $source = @file_get_contents($file);

            if (false === $source) {
                continue;
            }

            $classNames = $this->classNameParser->parse($source);

            if (false === $classNames) {
                continue;
            }

            foreach ($classNames as $className) {
                $priority = null;

                foreach ($files[$file] as $fileRule) {
                    if (ScanRuleSource::Classmap === $fileRule->source()) {
                        $priority = 30;

                        continue;
                    }

                    $prefix = $fileRule->namespacePrefix() ?? '';

                    if ('' !== $prefix && !str_starts_with($className, $prefix)) {
                        continue;
                    }

                    $candidatePriority = ScanRuleSource::Psr4 === $fileRule->source() ? 20 : 10;

                    if (null === $priority || $candidatePriority > $priority) {
                        $priority = $candidatePriority;
                    }
                }

                if (null === $priority) {
                    continue;
                }

                $selected = $selectedClasses[$className] ?? null;

                if (null === $selected || $priority > $selected['priority']) {
                    $selectedClasses[$className] = [
                        'file'     => $file,
                        'priority' => $priority,
                    ];
                }
            }
        }

        $classMap = array_map(function ($selected) {
            return $selected['file'];
        }, $selectedClasses);

        ksort($classMap, SORT_STRING);

        return $classMap;
    }
}
