<?php

declare(strict_types=1);

namespace SuperKernel\Composer\ClassMap;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\ScanRuleInterface;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;
use SuperKernel\Composer\Enum\AutoloadScope;
use SuperKernel\Composer\Enum\ScanRuleKind;
use SuperKernel\Composer\Enum\ScanRuleSource;
use SuperKernel\Composer\Exception\ClassMapScanException;
use SuperKernel\Composer\Path\FilesystemPath;
use SuperKernel\Tokenizer\Contract\ClassNameParserInterface;
use Throwable;

use function array_keys;
use function array_map;
use function glob;
use function is_dir;
use function is_file;
use function is_string;
use function ksort;
use function ltrim;
use function realpath;
use function sort;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const GLOB_NOSORT;
use const SORT_STRING;

/**
 * Derives deterministic package class maps from validated Composer scan rules.
 */
final readonly class ClassMapBuilder
{
    public function __construct(private ClassNameParserInterface $classNameParser)
    {
    }

    /**
     * @param list<PackageInterface> $packages
     *
     * @return array<string, array<class-string, non-empty-string>> Class maps keyed by package name
     */
    public function build(array $packages): array
    {
        $classMaps = [];

        foreach ($packages as $package) {
            $scanRules = $package->scanRulesFor(AutoloadScope::Development);
            $classMaps[$package->name()] = $this->buildPackage($package, $scanRules);
        }

        return $classMaps;
    }

    /**
     * @return array<class-string, non-empty-string> Sorted class map
     */
    public function buildPackage(PackageInterface $package, ?ScanRuleSetInterface $scanRules = null): array
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
                    $this->collectFile($candidate, $packageRoot, $scanRules, $rule, $files);
                    break;

                case ScanRuleKind::Directory:
                    $this->collectDirectory($candidate, $packageRoot, $scanRules, $rule, $files);
                    break;

                case ScanRuleKind::Glob:
                    $this->collectGlob($candidate, $packageRoot, $scanRules, $rule, $files);
                    break;
            }
        }

        $filePaths = array_keys($files);
        sort($filePaths, SORT_STRING);
        $selectedClasses = [];

        foreach ($filePaths as $file) {
            $classNames = $this->classNameParser->parseFile($file);

            if (false === $classNames) {
                continue;
            }

            foreach ($classNames as $className) {
                $priority = self::priority($className, $files[$file]);

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

    /** @param array<string, list<ScanRuleInterface>> $files */
    private function collectFile(
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

    /** @param array<string, list<ScanRuleInterface>> $files */
    private function collectDirectory(
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
                    $this->collectFile($entry->getPathname(), $packageRoot, $scanRules, $rule, $files);
                }
            }
        } catch (Throwable $exception) {
            throw new ClassMapScanException(
                sprintf('Unable to scan class directory %s.', $canonicalDirectory),
                previous: $exception,
            );
        }
    }

    /** @param array<string, list<ScanRuleInterface>> $files */
    private function collectGlob(
        string               $pattern,
        string               $packageRoot,
        ScanRuleSetInterface $scanRules,
        ScanRuleInterface    $rule,
        array               &$files,
    ): void
    {
        $matches = glob($pattern, GLOB_NOSORT);

        if (false === $matches) {
            throw new ClassMapScanException(sprintf('Unable to expand classmap pattern %s.', $pattern));
        }

        sort($matches, SORT_STRING);

        foreach ($matches as $match) {
            if (is_dir($match)) {
                $this->collectDirectory($match, $packageRoot, $scanRules, $rule, $files);
            } elseif (is_file($match)) {
                $this->collectFile($match, $packageRoot, $scanRules, $rule, $files);
            }
        }
    }

    /**
     * @param class-string            $className
     * @param list<ScanRuleInterface> $rules
     */
    private static function priority(string $className, array $rules): ?int
    {
        $priority = null;

        foreach ($rules as $rule) {
            if (ScanRuleSource::Classmap === $rule->source()) {
                $priority = 30;

                continue;
            }

            $prefix = $rule->namespacePrefix() ?? '';

            if ('' !== $prefix && !str_starts_with($className, $prefix)) {
                continue;
            }

            $candidatePriority = ScanRuleSource::Psr4 === $rule->source() ? 20 : 10;

            if (null === $priority || $candidatePriority > $priority) {
                $priority = $candidatePriority;
            }
        }

        return $priority;
    }
}
