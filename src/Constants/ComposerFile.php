<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Constants;

/**
 * Names the Composer project files that the metadata readers accept, by their exact basename.
 *
 * @internal
 */
enum ComposerFile: string
{
    case Json = 'composer.json';

    case Lock = 'composer.lock';
}
