<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use stdClass;
use SuperKernel\Composer\Constants\ScanRuleKind;
use SuperKernel\Composer\Constants\ScanRuleSource;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;
use SuperKernel\Composer\Exception\AutoloadRuleException;
use SuperKernel\Composer\Metadata\Autoload\AutoloadFileResolver;
use SuperKernel\Composer\Metadata\Autoload\AutoloadPath;
use SuperKernel\Composer\Model\ScanRule;
use SuperKernel\Composer\Model\ScanRuleSet;
use SuperKernel\Composer\Path\FilesystemPath;

use function array_is_list;
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

        foreach ([
            'psr-0' => ScanRuleSource::Psr0,
            'psr-4' => ScanRuleSource::Psr4,
        ] as $field => $source) {
            if (!property_exists($autoload, $field)) {
                continue;
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

                $context = sprintf('autoload.%s.%s', $field, $prefix);

                if (!is_string($paths) && (!is_array($paths) || !array_is_list($paths) || [] === $paths)) {
                    throw new AutoloadRuleException(sprintf(
                        '%s must be a string or a non-empty string list.',
                        $context,
                    ));
                }

                $paths = is_string($paths)
                    ? [
                        $paths,
                    ]
                    : $paths;

                foreach ($paths as $path) {
                    if (!is_string($path)) {
                        throw new AutoloadRuleException(sprintf('%s must contain only strings.', $context));
                    }
                }

                foreach ($paths as $path) {
                    $relativePath = AutoloadPath::normalize($path);

                    if (null === $packageRoot) {
                        continue;
                    }

                    $resolved = AutoloadPath::resolveExisting($packageRoot, $relativePath);

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

        if (property_exists($autoload, 'classmap')) {
            $classmap = $autoload->classmap;

            if (!is_array($classmap) || !array_is_list($classmap)) {
                throw new AutoloadRuleException('autoload.classmap must be a string list.');
            }

            foreach ($classmap as $path) {
                if (!is_string($path)) {
                    throw new AutoloadRuleException('autoload.classmap must contain only strings.');
                }

                $relativePath = AutoloadPath::normalize($path, true);
                $isGlob = str_contains($relativePath, '*');

                if (null === $packageRoot) {
                    continue;
                }

                if ($isGlob) {
                    $matches = glob($packageRoot . '/' . $relativePath, GLOB_NOSORT);
                    $hasSafeMatch = false;

                    foreach (false === $matches ? [] : $matches as $match) {
                        $resolvedMatch = realpath($match);

                        if (false === $resolvedMatch || (!is_dir($resolvedMatch) && !is_file($resolvedMatch))) {
                            continue;
                        }

                        $resolvedMatch = str_replace('\\', '/', $resolvedMatch);

                        if (!FilesystemPath::isWithin($resolvedMatch, $packageRoot)) {
                            throw new AutoloadRuleException(sprintf(
                                'Classmap glob "%s" resolves outside the package root.',
                                $relativePath,
                            ));
                        }

                        $hasSafeMatch = true;
                    }

                    if (!$hasSafeMatch) {
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

                $resolved = AutoloadPath::resolveExisting($packageRoot, $relativePath);

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

        AutoloadFileResolver::resolve($autoload, $packageRoot);

        if (property_exists($autoload, 'exclude-from-classmap')) {
            $patterns = $autoload->{'exclude-from-classmap'};

            if (!is_array($patterns) || !array_is_list($patterns)) {
                throw new AutoloadRuleException('autoload.exclude-from-classmap must be a string list.');
            }

            foreach ($patterns as $pattern) {
                if (!is_string($pattern)) {
                    throw new AutoloadRuleException('autoload.exclude-from-classmap must contain only strings.');
                }

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
                    || AutoloadPath::hasUnsupportedWildcard($pattern)
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

                $exclusions[] = ($anchored ? '/' : '') . $normalized;
            }
        }

        if (null === $packageRoot) {
            return ScanRuleSet::empty();
        }

        return new ScanRuleSet($rules, $exclusions);
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
        return AutoloadFileResolver::resolve($autoload, $packageRoot);
    }
}
