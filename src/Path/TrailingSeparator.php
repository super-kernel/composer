<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use function rtrim;

/**
 * Removes trailing separators from slash-normalized paths while keeping `/` and Windows drive roots intact.
 *
 * @internal
 */
final class TrailingSeparator
{
    public static function strip(string $path): string
    {
        if ('/' === $path || PathRoot::isWindowsDriveRoot($path)) {
            return $path;
        }

        return rtrim($path, '/');
    }
}
