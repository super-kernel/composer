<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Path;

use function str_replace;
use function str_starts_with;
use function strlen;

/**
 * Classifies path roots as absolute local, Phar, or Windows drive roots.
 *
 * @internal
 */
final class PathRoot
{
    public static function isAbsolute(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, 'phar://')
            || str_starts_with($path, '/')
            || WindowsDriveRoot::isPrefixOf($path);
    }

    /**
     * Reports whether a slash-normalized path consists of a Windows drive root only, such as `C:/`.
     */
    public static function isWindowsDriveRoot(string $path): bool
    {
        return 3 === strlen($path) && WindowsDriveRoot::isPrefixOf($path);
    }
}
