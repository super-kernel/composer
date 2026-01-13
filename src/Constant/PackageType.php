<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Constant;

enum PackageType: string
{
	case COMPOSER_PLUGIN = 'composer-plugin';

	case LIBRARY = 'library';

	case METAPACKAGE = 'metapackage';

	case PROJECT = 'project';
}
