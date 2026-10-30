<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Constants;

enum ScanRuleSource: string
{
    case Psr0 = 'psr-0';

    case Psr4 = 'psr-4';

    case Classmap = 'classmap';
}
