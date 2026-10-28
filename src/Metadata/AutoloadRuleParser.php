<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use stdClass;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;
use SuperKernel\Composer\Enum\ScanRuleKind;
use SuperKernel\Composer\Enum\ScanRuleSource;
use SuperKernel\Composer\Exception\AutoloadRuleException;
use SuperKernel\Composer\Model\ScanRule;
use SuperKernel\Composer\Model\ScanRuleSet;
use SuperKernel\Composer\Path\FilesystemPath;

use function array_is_list;
use function array_keys;
use function explode;
use function get_object_vars;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function ltrim;
use function property_exists;
use function realpath;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strpbrk;

use const GLOB_NOSORT;

final class AutoloadRuleParser
{
    /**
     * @throws AutoloadRuleException When target-dir or include-path is declared
     */
    public static function assertSupportedPackageFields(stdClass $package): void
    {
        foreach ([
                     'target-dir',
                     'include-path',
                 ] as $unsupportedField) {
            if (property_exists($package, $unsupportedField)) {
                throw new AutoloadRuleException(sprintf(
                    'Composer autoload feature "%s" is not supported.',
                    $unsupportedField,
                ));
            }
        }
    }

    /**
     * Parses one Composer autoload object relative to a verified package directory.
     *
     * All declarations are validated even when the package directory is unlocated.
     *
     * @param string|null $packageRoot Canonical package directory, or null when the package is unlocated
     *
     * @return ScanRuleSetInterface Empty set when no autoload object or located package directory is available
     *
     * @throws AutoloadRuleException When a declaration is malformed, unsafe, unsupported, or resolves outside the package
     */
    public static function parse(?stdClass $autoload, ?string $packageRoot): ScanRuleSetInterface
    {
        if (null === $autoload) {
            return ScanRuleSet::empty();
        }

        $rules = [];
        $exclusions = [];

        self::parsePsrMappings($autoload, 'psr-0', ScanRuleSource::Psr0, $packageRoot, $rules);
        self::parsePsrMappings($autoload, 'psr-4', ScanRuleSource::Psr4, $packageRoot, $rules);
        self::parseClassmap($autoload, $packageRoot, $rules);
        self::files($autoload, $packageRoot);
        self::parseExclusions($autoload, $exclusions);

        if (null === $packageRoot) {
            return ScanRuleSet::empty();
        }

        return new ScanRuleSet($rules, $exclusions);
    }

    /** @param list<ScanRule> $rules */
    private static function parsePsrMappings(
        stdClass       $autoload,
        string         $field,
        ScanRuleSource $source,
        ?string        $packageRoot,
        array         &$rules,
    ): void
    {
        if (!property_exists($autoload, $field)) {
            return;
        }

        $mappings = $autoload->{$field};

        if (!$mappings instanceof stdClass) {
            throw new AutoloadRuleException(sprintf('autoload.%s must be an object.', $field));
        }

        foreach (get_object_vars($mappings) as $prefix => $paths) {
            if (str_contains($prefix, "\0")) {
                throw new AutoloadRuleException(sprintf('autoload.%s contains a null byte in a prefix.', $field));
            }

            if ('psr-4' === $field && '' !== $prefix && !str_ends_with($prefix, '\\')) {
                throw new AutoloadRuleException(sprintf(
                    'PSR-4 namespace prefix "%s" must end in a backslash.',
                    $prefix,
                ));
            }

            foreach (self::mappingPaths($paths, sprintf('autoload.%s.%s', $field, $prefix)) as $path) {
                $relativePath = self::normalizeRelativePath($path);

                if (null === $packageRoot) {
                    continue;
                }

                $resolved = self::resolveExisting($packageRoot, $relativePath);

                if (null === $resolved) {
                    continue;
                }

                if (!is_dir($resolved)) {
                    continue;
                }

                $rules[] = new ScanRule(
                    $relativePath,
                    $source,
                    ScanRuleKind::Directory,
                    $prefix,
                );
            }
        }
    }

    /** @return list<string> */
    private static function mappingPaths(mixed $paths, string $context): array
    {
        if (is_string($paths)) {
            return [
                $paths,
            ];
        }

        if (!is_array($paths) || !array_is_list($paths) || [] === $paths) {
            throw new AutoloadRuleException(sprintf('%s must be a string or a non-empty string list.', $context));
        }

        foreach ($paths as $path) {
            if (!is_string($path)) {
                throw new AutoloadRuleException(sprintf('%s must contain only strings.', $context));
            }
        }

        return $paths;
    }

    private static function normalizeRelativePath(string $path, bool $allowGlob = false): string
    {
        if (str_contains($path, "\0")) {
            throw new AutoloadRuleException('Autoload paths must not contain null bytes.');
        }

        $path = str_replace('\\', '/', $path);

        if (FilesystemPath::isAbsolute($path)) {
            throw new AutoloadRuleException(sprintf('Autoload path "%s" must be package-relative.', $path));
        }

        if ($allowGlob) {
            if (false !== strpbrk($path, '?[]{}')) {
                throw new AutoloadRuleException('Classmap paths support only the "*" wildcard.');
            }
        } elseif (str_contains($path, '*')) {
            throw new AutoloadRuleException('Only classmap paths may contain the "*" wildcard.');
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                throw new AutoloadRuleException('Autoload paths must not contain ".." segments.');
            }

            $segments[] = $segment;
        }

        return [] === $segments ? '.' : implode('/', $segments);
    }

    private static function resolveExisting(string $packageRoot, string $relativePath): ?string
    {
        $candidate = '.' === $relativePath
            ? $packageRoot
            : $packageRoot . '/' . $relativePath;
        $resolved = realpath($candidate);

        if (false === $resolved) {
            return null;
        }

        $resolved = str_replace('\\', '/', $resolved);

        if (!FilesystemPath::isWithin($resolved, $packageRoot)) {
            throw new AutoloadRuleException(sprintf(
                'Autoload path "%s" resolves outside the package root.',
                $relativePath,
            ));
        }

        return $resolved;
    }

    /** @param list<ScanRule> $rules */
    private static function parseClassmap(stdClass $autoload, ?string $packageRoot, array &$rules): void
    {
        if (!property_exists($autoload, 'classmap')) {
            return;
        }

        $classmap = $autoload->classmap;

        if (!is_array($classmap) || !array_is_list($classmap)) {
            throw new AutoloadRuleException('autoload.classmap must be a string list.');
        }

        foreach ($classmap as $path) {
            if (!is_string($path)) {
                throw new AutoloadRuleException('autoload.classmap must contain only strings.');
            }

            $relativePath = self::normalizeRelativePath($path, true);
            $isGlob = str_contains($relativePath, '*');

            if (null === $packageRoot) {
                continue;
            }

            if ($isGlob) {
                if (!self::hasSafeGlobMatch($packageRoot, $relativePath)) {
                    continue;
                }

                $rules[] = new ScanRule(
                    $relativePath,
                    ScanRuleSource::Classmap,
                    ScanRuleKind::Glob,
                    null,
                );
                continue;
            }

            $resolved = self::resolveExisting($packageRoot, $relativePath);

            if (null === $resolved) {
                continue;
            }

            $kind = is_dir($resolved)
                ? ScanRuleKind::Directory
                : (is_file($resolved) ? ScanRuleKind::File : null);

            if (null !== $kind) {
                $rules[] = new ScanRule($relativePath, ScanRuleSource::Classmap, $kind, null);
            }
        }
    }

    private static function hasSafeGlobMatch(string $packageRoot, string $relativePattern): bool
    {
        $matches = glob($packageRoot . '/' . $relativePattern, GLOB_NOSORT);

        if (false === $matches || [] === $matches) {
            return false;
        }

        $hasSafeMatch = false;

        foreach ($matches as $match) {
            $resolved = realpath($match);

            if (false === $resolved || (!is_dir($resolved) && !is_file($resolved))) {
                continue;
            }

            $resolved = str_replace('\\', '/', $resolved);

            if (!FilesystemPath::isWithin($resolved, $packageRoot)) {
                throw new AutoloadRuleException(sprintf(
                    'Classmap glob "%s" resolves outside the package root.',
                    $relativePattern,
                ));
            }

            $hasSafeMatch = true;
        }

        return $hasSafeMatch;
    }

    /**
     * Resolves Composer file-autoload declarations without turning them into class-map scan rules.
     *
     * @param string|null $packageRoot Canonical package directory, or null when the package is unlocated
     *
     * @return list<non-empty-string> Canonical existing files in declaration order
     *
     * @throws AutoloadRuleException When a declaration is malformed, unsafe, or resolves outside the package
     *
     * @internal
     */
    public static function files(?stdClass $autoload, ?string $packageRoot): array
    {
        if (null === $autoload || !property_exists($autoload, 'files')) {
            return [];
        }

        $files = $autoload->files;

        if (!is_array($files) || !array_is_list($files)) {
            throw new AutoloadRuleException('autoload.files must be a string list.');
        }

        $resolvedFiles = [];

        foreach ($files as $file) {
            if (!is_string($file)) {
                throw new AutoloadRuleException('autoload.files must contain only strings.');
            }

            $relativePath = self::normalizeRelativePath($file);

            if (null === $packageRoot) {
                continue;
            }

            $resolved = self::resolveExisting($packageRoot, $relativePath);

            if (null !== $resolved && is_file($resolved)) {
                $resolvedFiles[$resolved] = true;
            }
        }

        return array_keys($resolvedFiles);
    }

    /** @param list<string> $exclusions */
    private static function parseExclusions(stdClass $autoload, array &$exclusions): void
    {
        if (!property_exists($autoload, 'exclude-from-classmap')) {
            return;
        }

        $patterns = $autoload->{'exclude-from-classmap'};

        if (!is_array($patterns) || !array_is_list($patterns)) {
            throw new AutoloadRuleException('autoload.exclude-from-classmap must be a string list.');
        }

        foreach ($patterns as $pattern) {
            if (!is_string($pattern)) {
                throw new AutoloadRuleException('autoload.exclude-from-classmap must contain only strings.');
            }

            $exclusions[] = self::normalizeExclusionPattern($pattern);
        }
    }

    private static function normalizeExclusionPattern(string $pattern): string
    {
        if (str_contains($pattern, "\0")) {
            throw new AutoloadRuleException('Classmap exclusion patterns must not contain null bytes.');
        }

        $pattern = str_replace('\\', '/', $pattern);
        $anchored = str_starts_with($pattern, '/');
        $trailingSlash = str_ends_with($pattern, '/');
        $relativePattern = ltrim($pattern, '/');

        if (
            str_starts_with($pattern, '//')
            || FilesystemPath::isAbsolute($relativePattern)
            || false !== strpbrk($pattern, '?[]{}')
        ) {
            throw new AutoloadRuleException('Invalid classmap exclusion pattern.');
        }

        $segments = [];

        foreach (explode('/', $relativePattern) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                throw new AutoloadRuleException('Classmap exclusion patterns must not contain ".." segments.');
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);

        if ($trailingSlash && '' !== $normalized) {
            $normalized .= '/';
        }

        return ($anchored ? '/' : '') . $normalized;
    }
}
