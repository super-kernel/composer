<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use function array_pop;
use function explode;
use function implode;
use function is_dir;
use function is_file;
use function ltrim;
use function realpath;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Resolves project directories and normalizes cross-platform filesystem paths without creating them.
 */
final class FilesystemPath
{
    public static function isAbsolute(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, 'phar://')
            || str_starts_with($path, '/')
            || self::hasWindowsDriveRoot($path);
    }

    /**
     * Resolves a path against a base directory and normalizes its segments.
     *
     * Existing paths are canonicalized through realpath; missing paths retain their normalized absolute form.
     *
     * @return string|null Normalized absolute path, or null for an empty, null-byte, or escaping path
     */
    public static function absolute(string $baseDirectory, string $path): ?string
    {
        if ('' === $path || str_contains($path, "\0")) {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $baseDirectory = str_replace('\\', '/', $baseDirectory);
        $phar = str_starts_with($path, 'phar://');

        if ($phar) {
            $path = substr($path, 7);
        } elseif (str_starts_with($baseDirectory, 'phar://') && !self::isAbsolute($path)) {
            $phar = true;
            $baseDirectory = substr($baseDirectory, 7);
        }

        $candidate = self::isAbsolute($path)
            ? $path
            : rtrim($baseDirectory, '/') . '/' . $path;
        $prefix = '';

        if (self::hasWindowsDriveRoot($candidate)) {
            $prefix = substr($candidate, 0, 3);
            $candidate = substr($candidate, 3);
        } elseif (str_starts_with($candidate, '/')) {
            $prefix = '/';
            $candidate = ltrim($candidate, '/');
        }

        $segments = [];

        foreach (explode('/', $candidate) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] === $segments) {
                    return null;
                }

                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = $prefix . implode('/', $segments);

        if ($phar) {
            return 'phar://' . self::withoutTrailingSeparator($normalized);
        }

        $resolved = realpath($normalized);

        return false === $resolved
            ? self::withoutTrailingSeparator($normalized)
            : self::withoutTrailingSeparator(str_replace('\\', '/', $resolved));
    }

    /**
     * @return string|null Canonical directory using `/` separators, or null when the path is not an existing directory
     */
    public static function canonicalDirectory(?string $path): ?string
    {
        if (null === $path || '' === $path || str_contains($path, "\0")) {
            return null;
        }

        if (str_starts_with($path, 'phar://')) {
            $normalized = self::absolute('', $path);

            return null !== $normalized && is_dir($normalized) ? $normalized : null;
        }

        $resolved = realpath($path);

        if (false === $resolved || !is_dir($resolved)) {
            return null;
        }

        return self::withoutTrailingSeparator(str_replace('\\', '/', $resolved));
    }

    /**
     * Checks the supplied directory for Composer files without reading them or searching parent directories.
     *
     * @return non-empty-string|null Canonical directory, or null when the directory or required files are unavailable
     */
    public static function findProjectDirectory(?string $directory, bool $requireLockFile = false): ?string
    {
        $candidate = self::canonicalDirectory($directory);

        if (
            null === $candidate
            || !is_file($candidate . '/composer.json')
            || ($requireLockFile && !is_file($candidate . '/composer.lock'))
        ) {
            return null;
        }

        return $candidate;
    }

    public static function isWithin(string $path, string $directory): bool
    {
        $path = self::withoutTrailingSeparator(str_replace('\\', '/', $path));
        $directory = self::withoutTrailingSeparator(str_replace('\\', '/', $directory));

        if ($path === $directory) {
            return true;
        }

        if ('/' === $directory) {
            return str_starts_with($path, '/');
        }

        if (self::isWindowsDriveRoot($directory)) {
            return str_starts_with(strtolower($path), strtolower($directory));
        }

        return str_starts_with($path, $directory . '/');
    }

    private static function withoutTrailingSeparator(string $path): string
    {
        if ('/' === $path || self::isWindowsDriveRoot($path)) {
            return $path;
        }

        return rtrim($path, '/');
    }

    private static function hasWindowsDriveRoot(string $path): bool
    {
        if (3 > strlen($path) || ':' !== $path[1] || '/' !== $path[2]) {
            return false;
        }

        $drive = $path[0];

        return ('A' <= $drive && 'Z' >= $drive)
            || ('a' <= $drive && 'z' >= $drive);
    }

    private static function isWindowsDriveRoot(string $path): bool
    {
        return 3 === strlen($path) && self::hasWindowsDriveRoot($path);
    }
}
