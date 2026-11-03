<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use function str_starts_with;
use function strlen;
use function substr;

/**
 * Converts slash-normalized paths below a directory to directory-relative form and back, leaving other paths absolute.
 *
 * @internal
 */
final class RelativePath
{
    /**
     * Returns `.` for the directory itself, a relative path for a descendant, and any other path unchanged.
     */
    public static function relativize(string $path, string $directory): string
    {
        $directory = TrailingSeparator::strip($directory);

        if ($path === $directory) {
            return '.';
        }

        return str_starts_with($path, $directory . '/') ? substr($path, strlen($directory) + 1) : $path;
    }

    /**
     * Resolves a path produced by relativize() against the directory; absolute paths stay unchanged.
     */
    public static function resolve(string $path, string $directory): string
    {
        if (PathRoot::isAbsolute($path)) {
            return $path;
        }

        $directory = TrailingSeparator::strip($directory);

        return '.' === $path ? $directory : $directory . '/' . $path;
    }
}
