<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use SuperKernel\Composer\Path\FilesystemPath;
use SuperKernel\Composer\Tests\Support\TestCase;

final class FilesystemPathTest extends TestCase
{
    public function testPreservesFilesystemRootsDuringCanonicalBoundaryChecks(): void
    {
        self::assertSame('/', FilesystemPath::canonicalDirectory('/'));
        self::assertSame('/', FilesystemPath::absolute('/', '.'));
        self::assertTrue(FilesystemPath::isWithin('/tmp/example', '/'));
        self::assertTrue(FilesystemPath::isAbsolute('C:\\project'));
        self::assertFalse(FilesystemPath::isAbsolute('C:project'));
        self::assertFalse(FilesystemPath::isAbsolute('1:/project'));
        self::assertSame(
            'Z:/super-kernel-path-test',
            FilesystemPath::absolute('/ignored', 'Z:/super-kernel-path-test'),
        );
        self::assertTrue(FilesystemPath::isWithin('C:/project/file.php', 'c:/'));
        self::assertFalse(FilesystemPath::isWithin('D:/project/file.php', 'C:/'));
    }
}
