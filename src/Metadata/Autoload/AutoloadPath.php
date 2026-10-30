<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Autoload;

use SuperKernel\Composer\Exception\AutoloadRuleException;
use SuperKernel\Composer\Path\FilesystemPath;

use function explode;
use function implode;
use function realpath;
use function sprintf;
use function str_contains;
use function str_replace;
use function strpbrk;

/**
 * Validates package-relative autoload paths and resolves existing ones inside their package root.
 *
 * @internal
 */
final class AutoloadPath
{
    private const string UNSUPPORTED_WILDCARDS = '?[]{}';

    /**
     * @param bool $allowGlob Whether the classmap `*` wildcard is accepted
     *
     * @return string Slash-separated path without empty or `.` segments, or `.` for the package root itself
     *
     * @throws AutoloadRuleException When the path contains a null byte or a `..` segment, is absolute, or uses an
     *                               unsupported wildcard
     */
    public static function normalize(string $path, bool $allowGlob = false): string
    {
        if (str_contains($path, "\0")) {
            throw new AutoloadRuleException('Autoload paths must not contain null bytes.');
        }

        $path = str_replace('\\', '/', $path);

        if (FilesystemPath::isAbsolute($path)) {
            throw new AutoloadRuleException(sprintf('Autoload path "%s" must be package-relative.', $path));
        }

        if ($allowGlob) {
            if (false !== strpbrk($path, self::UNSUPPORTED_WILDCARDS)) {
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

    /**
     * @return string|null Canonical slash-separated path, or null when the path does not exist
     *
     * @throws AutoloadRuleException When the existing path resolves outside the package root
     */
    public static function resolveExisting(string $packageRoot, string $relativePath): ?string
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

    /**
     * Reports whether a path or pattern contains a glob character other than the supported `*` wildcard.
     */
    public static function hasUnsupportedWildcard(string $path): bool
    {
        return false !== strpbrk($path, self::UNSUPPORTED_WILDCARDS);
    }
}
