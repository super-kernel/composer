<?php
declare(strict_types=1);

use SuperKernel\Composer\Factory\PackageMetadataRegistryFactory;
use SuperKernel\Composer\Factory\PathLocatorFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$pathLocator = new PathLocatorFactory()(dirname(__DIR__));

$packageMetadataRegistry = new PackageMetadataRegistryFactory()($pathLocator);