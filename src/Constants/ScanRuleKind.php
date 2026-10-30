<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Constants;

enum ScanRuleKind: string
{
    case Directory = 'directory';

    case File = 'file';

    /** A safe Composer classmap `*` glob with at least one existing match. */
    case Glob = 'glob';
}
