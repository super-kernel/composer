<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Enum;

/**
 * Composer's built-in and reserved package types, plus the metadata view's sole root package.
 */
enum PackageType: string
{
    /** Synthetic type; the original composer.json type remains available in package metadata. */
    case Root = 'Root';

    case Library = 'library';

    case Project = 'project';

    case Metapackage = 'metapackage';

    case ComposerPlugin = 'composer-plugin';

    case PhpExt = 'php-ext';

    case PhpExtZend = 'php-ext-zend';
}
