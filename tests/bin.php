<?php
declare(strict_types=1);

use SuperKernel\Composer\Factory\PackageMetadataRegistryFactory;

require_once __DIR__ . '/../vendor/autoload.php';

var_dump(
	new PackageMetadataRegistryFactory()()->getPackages(),
);