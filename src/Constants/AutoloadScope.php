<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Constants;

/**
 * Selects which root-package autoload declarations contribute scan rules.
 */
enum AutoloadScope: string
{
    /** Includes only the root `autoload` declaration. */
    case Production = 'production';

    /** Merges the root `autoload` and `autoload-dev` declarations. */
    case Development = 'development';
}
