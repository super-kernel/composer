<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use SuperKernel\Composer\Metadata\ComposerPackageName;
use SuperKernel\Composer\Tests\Support\TestCase;

final class ComposerPackageNameTest extends TestCase
{
    public function testAcceptsCurrentComposerPackageNameGrammarAndRejectsNonLowercaseNames(): void
    {
        foreach ([
            'vendor/package',
            'vendor.name/package_name',
            'vendor-name/package--name',
            'v1/p2',
        ] as $name) {
            $this->assertTrue(ComposerPackageName::isPackage($name), $name);
        }

        foreach ([
            'Vendor/package',
            'vendor/Package',
            'vendor',
            '/package',
            'vendor/',
            'vendor/package---name',
            'vendor//package',
        ] as $name) {
            $this->assertFalse(ComposerPackageName::isPackage($name), $name);
        }
    }

    public function testRecognizesEveryCurrentComposerPlatformPackageFamily(): void
    {
        foreach ([
            'php',
            'php-64bit',
            'php-ipv6',
            'php-zts',
            'php-debug',
            'hhvm',
            'ext-json',
            'ext-zend-opcache',
            'ext-some.extension',
            'lib-curl',
            'lib-pq-libpq',
            'composer',
            'composer-plugin-api',
            'composer-runtime-api',
        ] as $name) {
            $this->assertTrue(ComposerPackageName::isPlatform($name), $name);
        }

        foreach ([
            'PHP',
            'php-32bit',
            'php-cli',
            'ext-',
            'lib-',
            'composer-api',
            'vendor/package',
            'not-a-platform-package',
        ] as $name) {
            $this->assertFalse(ComposerPackageName::isPlatform($name), $name);
        }
    }

    public function testRecognizesOnlyPhpExtensionPlatformRequirementsAsExtensions(): void
    {
        foreach ([
            'ext-json',
            'ext-swoole',
            'ext-zend-opcache',
            'ext-some.extension',
        ] as $name) {
            $this->assertTrue(ComposerPackageName::isExtension($name), $name);
        }

        foreach ([
            'ext-',
            'EXT-json',
            'php',
            'lib-curl',
            'composer-runtime-api',
            'vendor/package',
        ] as $name) {
            $this->assertFalse(ComposerPackageName::isExtension($name), $name);
        }
    }
}
