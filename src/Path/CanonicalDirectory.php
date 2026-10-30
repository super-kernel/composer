<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use function is_dir;
use function realpath;
use function str_contains;
use function str_replace;
use function str_starts_with;

/**
 * Resolves existing local or Phar directories to their canonical slash-separated form.
 *
 * @internal
 */
final class CanonicalDirectory
{
    /**
     * @return string|null Canonical directory using `/` separators, or null when the path is not an existing directory
     */
    public static function resolve(?string $path): ?string
    {
        if (null === $path || '' === $path || str_contains($path, "\0")) {
            return null;
        }

        if (str_starts_with($path, 'phar://')) {
            $normalized = AbsolutePath::resolve('', $path);

            return null !== $normalized && is_dir($normalized) ? $normalized : null;
        }

        $resolved = realpath($path);

        if (false === $resolved || !is_dir($resolved)) {
            return null;
        }

        return TrailingSeparator::strip(str_replace('\\', '/', $resolved));
    }
}
