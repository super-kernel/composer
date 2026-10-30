<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use function strlen;

/**
 * Detects a Windows drive root such as `C:/` at the start of a slash-normalized path.
 *
 * @internal
 */
final class WindowsDriveRoot
{
    public static function isPrefixOf(string $path): bool
    {
        if (3 > strlen($path) || ':' !== $path[1] || '/' !== $path[2]) {
            return false;
        }

        $drive = $path[0];

        return ('A' <= $drive && 'Z' >= $drive)
            || ('a' <= $drive && 'z' >= $drive);
    }
}
