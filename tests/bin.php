<?php
declare(strict_types=1);

use SuperKernel\Composer\Factory\PackageMetadataRegistryFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$packageMetadataRegistry = new PackageMetadataRegistryFactory()();

var_dump(
	$packageMetadataRegistry->getPackage('super-kernel/path-resolver'),
);