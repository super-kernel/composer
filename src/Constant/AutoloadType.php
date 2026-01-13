<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Constant;

enum AutoloadType: string
{
	case FILES = 'files';

	case PSR_0 = 'psr-0';

	case PSR_4 = 'psr-4';
}
