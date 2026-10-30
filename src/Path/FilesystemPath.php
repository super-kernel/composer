<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use SuperKernel\Composer\Constants\ComposerFile;

use function is_file;
use function str_replace;
use function str_starts_with;
use function strtolower;

/**
 * Resolves project directories and normalizes cross-platform filesystem paths without creating them.
 */
final class FilesystemPath
{
    public static function isAbsolute(string $path): bool
    {
        return PathRoot::isAbsolute($path);
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
        return AbsolutePath::resolve($baseDirectory, $path);
    }

    /**
     * @return string|null Canonical directory using `/` separators, or null when the path is not an existing directory
     */
    public static function canonicalDirectory(?string $path): ?string
    {
        return CanonicalDirectory::resolve($path);
    }

    /**
     * Checks the supplied directory for Composer files without reading them or searching parent directories.
     *
     * @return non-empty-string|null Canonical directory, or null when the directory or required files are unavailable
     */
    public static function findProjectDirectory(?string $directory, bool $requireLockFile = false): ?string
    {
        $candidate = CanonicalDirectory::resolve($directory);

        if (
            null === $candidate
            || !is_file($candidate . '/' . ComposerFile::Json->value)
            || ($requireLockFile && !is_file($candidate . '/' . ComposerFile::Lock->value))
        ) {
            return null;
        }

        return $candidate;
    }

    public static function isWithin(string $path, string $directory): bool
    {
        $path = TrailingSeparator::strip(str_replace('\\', '/', $path));
        $directory = TrailingSeparator::strip(str_replace('\\', '/', $directory));

        if ($path === $directory) {
            return true;
        }

        if ('/' === $directory) {
            return str_starts_with($path, '/');
        }

        if (PathRoot::isWindowsDriveRoot($directory)) {
            return str_starts_with(strtolower($path), strtolower($directory));
        }

        return str_starts_with($path, $directory . '/');
    }
}
