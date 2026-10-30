<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use function array_pop;
use function explode;
use function implode;
use function ltrim;
use function realpath;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function substr;

/**
 * Resolves paths against a base directory into normalized absolute local or Phar paths.
 *
 * @internal
 */
final class AbsolutePath
{
    /**
     * Existing paths are canonicalized through realpath; missing paths retain their normalized absolute form.
     *
     * @return string|null Normalized absolute path, or null for an empty, null-byte, or escaping path
     */
    public static function resolve(string $baseDirectory, string $path): ?string
    {
        if ('' === $path || str_contains($path, "\0")) {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $baseDirectory = str_replace('\\', '/', $baseDirectory);
        $phar = str_starts_with($path, 'phar://');

        if ($phar) {
            $path = substr($path, 7);
        } elseif (str_starts_with($baseDirectory, 'phar://') && !PathRoot::isAbsolute($path)) {
            $phar = true;
            $baseDirectory = substr($baseDirectory, 7);
        }

        $candidate = PathRoot::isAbsolute($path)
            ? $path
            : rtrim($baseDirectory, '/') . '/' . $path;
        $prefix = '';

        if (WindowsDriveRoot::isPrefixOf($candidate)) {
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
            return 'phar://' . TrailingSeparator::strip($normalized);
        }

        $resolved = realpath($normalized);

        return false === $resolved
            ? TrailingSeparator::strip($normalized)
            : TrailingSeparator::strip(str_replace('\\', '/', $resolved));
    }
}
