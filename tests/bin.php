<?php
declare(strict_types=1);

use SuperKernel\Composer\Factory\PackageMetadataFactory;
use SuperKernel\Composer\Factory\ComposerConfigFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$pathLocator = new ComposerConfigFactory()(dirname(__DIR__));

$packageMetadataRegistry = new PackageMetadataFactory()($pathLocator);

var_dump($packageMetadataRegistry);