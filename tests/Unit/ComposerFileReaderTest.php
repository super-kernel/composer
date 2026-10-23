<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use SuperKernel\Composer\Contract\ComposerJsonInterface;
use SuperKernel\Composer\Contract\ComposerLockInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Metadata\ComposerFileReader;
use SuperKernel\Composer\Tests\Support\TestCase;
use Throwable;

final class ComposerFileReaderTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReadsEveryOfficialComposerJsonFieldAndPreservesCustomFields(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $fields = [
                'name'        => 'acme/example',
                'description' => 'Example package',
                'license'     => [
                    'MIT',
                ],
                'type'                 => 'library',
                'abandoned'            => false,
                'version'              => '1.2.3',
                'default-branch'       => true,
                'non-feature-branches' => [
                    'latest-.*',
                ],
                'keywords' => [
                    'example',
                ],
                'readme'  => 'README.md',
                'time'    => '2026-09-04T00:00:00Z',
                'authors' => [
                    [
                        'name'     => 'Example Author',
                        'email'    => 'author@example.com',
                        'homepage' => 'https://example.com/author',
                        'role'     => 'Developer',
                    ],
                ],
                'homepage' => 'https://example.com',
                'support'  => [
                    'issues'   => 'https://example.com/issues',
                    'security' => 'https://example.com/security',
                ],
                'funding' => [
                    [
                        'type' => 'custom',
                        'url'  => 'https://example.com/fund',
                    ],
                ],
                'source' => [
                    'type'      => 'git',
                    'url'       => 'https://example.com/source.git',
                    'reference' => 'abc123',
                ],
                'dist' => [
                    'type'      => 'zip',
                    'url'       => 'https://example.com/archive.zip',
                    'reference' => 'abc123',
                    'shasum'    => '',
                ],
                '_comment' => [
                    'A comment',
                ],
                'require' => [
                    'php' => '^8.4',
                ],
                'require-dev' => [
                    'phpunit/phpunit' => '^13.0',
                ],
                'replace' => [
                    'acme/old' => 'self.version',
                ],
                'conflict' => [
                    'acme/conflict' => '<1.0',
                ],
                'provide' => [
                    'acme/contract' => '1.0',
                ],
                'suggest' => [
                    'ext-example' => 'Enables examples',
                ],
                'repositories' => [
                    [
                        'type' => 'vcs',
                        'url'  => 'https://example.com/source.git',
                    ],
                ],
                'minimum-stability' => 'dev',
                'prefer-stable'     => true,
                'autoload'          => [
                    'psr-4' => [
                        'Acme\\Example\\' => 'src/',
                    ],
                    'classmap' => [
                        'legacy/',
                    ],
                    'files' => [
                        'src/functions.php',
                    ],
                    'exclude-from-classmap' => [
                        '/tests/',
                    ],
                ],
                'autoload-dev' => [
                    'psr-4' => [
                        'Acme\\Example\\Tests\\' => 'tests/',
                    ],
                ],
                'target-dir'   => 'legacy/',
                'include-path' => [
                    'legacy/',
                ],
                'bin' => [
                    'bin/example',
                ],
                'archive' => [
                    'name'    => 'example',
                    'exclude' => [
                        '/tests/',
                    ],
                ],
                'php-ext' => [
                    'extension-name' => 'ext-example',
                ],
                'config' => [
                    'sort-packages' => true,
                ],
                'extra' => [
                    'custom' => [
                        'value' => 1,
                    ],
                ],
                'scripts' => [
                    'test' => [
                        'phpunit',
                    ],
                ],
                'scripts-descriptions' => [
                    'test' => 'Run tests',
                ],
                'scripts-aliases' => [
                    'test' => [
                        'tests',
                    ],
                ],
                'custom-field' => [
                    'preserved' => true,
                ],
            ];
            $path = $root . '/composer.json';
            $this->writeJson($path, $fields);

            $composerJson = ComposerFileReader::read($path);

            $this->assertInstanceOf(ComposerJsonInterface::class, $composerJson);
            $this->assertSame($fields, $composerJson->all());

            foreach ([
                'name'                 => 'name',
                'description'          => 'description',
                'license'              => 'license',
                'type'                 => 'type',
                'abandoned'            => 'abandoned',
                'version'              => 'version',
                'default-branch'       => 'defaultBranch',
                'non-feature-branches' => 'nonFeatureBranches',
                'keywords'             => 'keywords',
                'readme'               => 'readme',
                'time'                 => 'time',
                'authors'              => 'authors',
                'homepage'             => 'homepage',
                'support'              => 'support',
                'funding'              => 'funding',
                'source'               => 'source',
                'dist'                 => 'dist',
                '_comment'             => 'comment',
                'require'              => 'require',
                'require-dev'          => 'requireDev',
                'replace'              => 'replace',
                'conflict'             => 'conflict',
                'provide'              => 'provide',
                'suggest'              => 'suggest',
                'repositories'         => 'repositories',
                'minimum-stability'    => 'minimumStability',
                'prefer-stable'        => 'preferStable',
                'autoload'             => 'autoload',
                'autoload-dev'         => 'autoloadDev',
                'target-dir'           => 'targetDir',
                'include-path'         => 'includePath',
                'bin'                  => 'bin',
                'archive'              => 'archive',
                'php-ext'              => 'phpExt',
                'config'               => 'config',
                'extra'                => 'extra',
                'scripts'              => 'scripts',
                'scripts-descriptions' => 'scriptsDescriptions',
                'scripts-aliases'      => 'scriptsAliases',
            ] as $field => $method) {
                $this->assertSame($fields[$field], $composerJson->{$method}(), $field);
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReadsEveryOfficialComposerLockFieldAndPreservesCustomFields(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $fields = [
                '_readme' => [
                    'Generated file',
                ],
                'content-hash' => 'content-hash',
                'packages'     => [
                    [
                        'name'    => 'acme/runtime',
                        'version' => '1.0.0',
                    ],
                ],
                'packages-dev' => [
                    [
                        'name'    => 'acme/development',
                        'version' => 'dev-main',
                    ],
                ],
                'aliases' => [
                    [
                        'package'          => 'acme/runtime',
                        'version'          => 'dev-main',
                        'alias'            => '1.0.x-dev',
                        'alias_normalized' => '1.0.9999999.9999999-dev',
                    ],
                ],
                'minimum-stability' => 'stable',
                'stability-flags'   => [
                    'acme/development' => 20,
                ],
                'prefer-stable' => true,
                'prefer-lowest' => false,
                'platform'      => [
                    'php' => '^8.4',
                ],
                'platform-dev' => [
                    'ext-json' => '*',
                ],
                'platform-overrides' => [
                    'php' => '8.4.0',
                ],
                'plugin-api-version' => '2.6.0',
                'custom-field'       => [
                    'preserved' => true,
                ],
            ];
            $path = $root . '/composer.lock';
            $this->writeJson($path, $fields);

            $composerLock = ComposerFileReader::read($path);

            $this->assertInstanceOf(ComposerLockInterface::class, $composerLock);
            $this->assertSame($fields, $composerLock->all());

            foreach ([
                '_readme'            => 'readme',
                'content-hash'       => 'contentHash',
                'packages'           => 'packages',
                'packages-dev'       => 'packagesDev',
                'aliases'            => 'aliases',
                'minimum-stability'  => 'minimumStability',
                'stability-flags'    => 'stabilityFlags',
                'prefer-stable'      => 'preferStable',
                'prefer-lowest'      => 'preferLowest',
                'platform'           => 'platform',
                'platform-dev'       => 'platformDev',
                'platform-overrides' => 'platformOverrides',
                'plugin-api-version' => 'pluginApiVersion',
            ] as $field => $method) {
                $this->assertSame($fields[$field], $composerLock->{$method}(), $field);
            }
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReturnsNullForAbsentOptionalFields(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeJson($root . '/composer.json', (object) []);
            $this->writeJson($root . '/composer.lock', [
                'content-hash'    => '',
                'packages'        => [],
                'packages-dev'    => [],
                'platform'        => [],
                'platform-dev'    => [],
                'stability-flags' => [],
            ]);

            $composerJson = ComposerFileReader::readComposerJson($root . '/composer.json');
            $composerLock = ComposerFileReader::readComposerLock($root . '/composer.lock');

            $this->assertNull($composerJson->name());
            $this->assertNull($composerJson->scriptsAliases());
            $this->assertNull($composerLock->readme());
            $this->assertNull($composerLock->pluginApiVersion());
            $this->assertSame([], $composerLock->platform());
            $this->assertSame([], $composerLock->platformDev());
            $this->assertSame([], $composerLock->stabilityFlags());
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsUnsupportedUnreadableAndMalformedDocuments(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::read($root . '/metadata.json'),
                'Expected a composer.json or composer.lock path',
            );
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::read($root . '/composer.json'),
                'unavailable or not a regular file',
            );

            $this->writeFile($root . '/composer.json', '{invalid');
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::read($root . '/composer.json'),
                'Invalid JSON',
            );

            $this->writeJson($root . '/composer.json', []);
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::read($root . '/composer.json'),
                'must contain a JSON object',
            );
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsInvalidOfficialFieldStructures(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeJson($root . '/composer.json', [
                'name' => 'Invalid/Package',
            ]);
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::readComposerJson($root . '/composer.json'),
                'lowercase Composer package name',
            );

            $this->writeJson($root . '/composer.json', [
                'scripts' => [
                    'test' => 42,
                ],
            ]);
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::readComposerJson($root . '/composer.json'),
                'scripts.test',
            );

            $this->writeJson($root . '/composer.lock', [
                'packages'     => [],
                'packages-dev' => [],
            ]);
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::readComposerLock($root . '/composer.lock'),
                'content-hash',
            );

            $this->writeJson($root . '/composer.lock', [
                'content-hash' => 'hash',
                'packages'     => [
                    [
                        'name' => 'acme/incomplete',
                    ],
                ],
                'packages-dev' => [],
            ]);
            $this->assertThrows(
                ComposerFileException::class,
                static fn() => ComposerFileReader::readComposerLock($root . '/composer.lock'),
                'version',
            );
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testValidateReportsValidityWithoutLeakingExceptions(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->writeJson($root . '/composer.json', [
                'name' => 'acme/example',
            ]);
            $this->writeFile($root . '/composer.lock', '{invalid');

            $this->assertTrue(ComposerFileReader::validate($root . '/composer.json'));
            $this->assertFalse(ComposerFileReader::validate($root . '/composer.lock'));
            $this->assertFalse(ComposerFileReader::validate($root . '/metadata.json'));
        });
    }
}
