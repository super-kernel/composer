<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use SuperKernel\Composer\Path\RelativePath;
use SuperKernel\Composer\Tests\Support\TestCase;

final class RelativePathTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function paths(): iterable
    {
        yield 'the directory itself' => [
            '/project/vendor',
            '/project/vendor/',
            '.',
        ];
        yield 'a descendant' => [
            '/project/vendor/acme/a/src/A.php',
            '/project/vendor',
            'acme/a/src/A.php',
        ];
        yield 'a sibling with a shared prefix' => [
            '/project/vendor-old/acme/a',
            '/project/vendor',
            '/project/vendor-old/acme/a',
        ];
        yield 'a path outside the directory' => [
            '/elsewhere/acme/a',
            '/project/vendor',
            '/elsewhere/acme/a',
        ];
        yield 'a Phar descendant' => [
            'phar:///opt/app.phar/vendor/acme/a',
            'phar:///opt/app.phar/vendor',
            'acme/a',
        ];
    }

    #[DataProvider('paths')]
    public function testRelativizesDescendantsAndKeepsOtherPathsAbsolute(
        string $path,
        string $directory,
        string $expected,
    ): void
    {
        self::assertSame($expected, RelativePath::relativize($path, $directory));
    }

    public function testResolvesRelativePathsAgainstAnotherDirectory(): void
    {
        self::assertSame('phar:///opt/app.phar', RelativePath::resolve('.', 'phar:///opt/app.phar/'));
        self::assertSame('phar:///opt/app.phar/src/A.php', RelativePath::resolve('src/A.php', 'phar:///opt/app.phar'));
        self::assertSame('/elsewhere/A.php', RelativePath::resolve('/elsewhere/A.php', '/project'));
        self::assertSame('phar:///other.phar/A.php', RelativePath::resolve('phar:///other.phar/A.php', '/project'));
    }
}
