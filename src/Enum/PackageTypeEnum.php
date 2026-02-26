<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Enum;

enum PackageTypeEnum: string
{
	case LIBRARY = 'library';

	case COMPOSER_PLUGIN = 'composer-plugin';

	case METAPACKAGE = 'metapackage';

	case PROJECT = 'project';
}
