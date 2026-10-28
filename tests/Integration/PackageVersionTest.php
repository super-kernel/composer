<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Metadata\ComposerFileReader;
use SuperKernel\Composer\Tests\Support\TestCase;
use Throwable;

final class PackageVersionTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function versions(): iterable
    {
        yield 'ordinary' => [
            '1.0.0-dev',
        ];
        yield 'empty' => [
            '',
        ];
        yield 'whitespace' => [
            ' release ',
        ];
        yield 'control characters' => [
            "build\n\x00\t",
        ];
        yield 'Symfony sentinel' => [
            'UNKNOWN',
        ];
        yield 'prefix and suffix' => [
            'v1.2.3+custom',
        ];
        yield 'arbitrary string' => [
            'not a Composer version',
        ];
    }

    /** @throws Throwable When reading or adapting the fixture fails */
    #[DataProvider('versions')]
    public function testRootAndLockVersionsRemainExactStrings(string $version): void
    {
        $this->withTemporaryDirectory(function (string $root) use ($version): void {
            $this->writeJson($root . '/composer.json', [
                'name'    => 'acme/application',
                'version' => $version,
            ]);
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => 'fixture',
                'packages'     => [
                    [
                        'name'    => 'acme/dependency',
                        'version' => $version,
                    ],
                ],
                'packages-dev' => [],
            ]);
            $this->writeFile($root . '/vendor/acme/dependency/.keep');

            self::assertSame($version, ComposerFileReader::readComposerJson($root . '/composer.json')->version());
            $packages = $this->readPackages($root);
            self::assertSame($version, $packages->root()->version());
            self::assertSame($version, $packages->get('acme/dependency')?->version());
        });
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidTypes(): iterable
    {
        yield 'number' => [
            42,
        ];
        yield 'array' => [
            [],
        ];
        yield 'boolean' => [
            false,
        ];
        yield 'null' => [
            null,
        ];
    }

    /** @throws Throwable When creating or reading fixture documents fails */
    #[DataProvider('invalidTypes')]
    public function testRootAndLockRejectNonStringVersions(mixed $version): void
    {
        $this->withTemporaryDirectory(function (string $root) use ($version): void {
            $this->writeJson($root . '/composer.json', [
                'name'    => 'acme/application',
                'version' => $version,
            ]);
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => 'fixture',
                'packages'     => [
                    [
                        'name'    => 'acme/dependency',
                        'version' => $version,
                    ],
                ],
                'packages-dev' => [],
            ]);
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::readComposerJson($root . '/composer.json'),
                'must be string',
            );
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::readComposerLock($root . '/composer.lock'),
                'must be string',
            );
        });
    }
}
