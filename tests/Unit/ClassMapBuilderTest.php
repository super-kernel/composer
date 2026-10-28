<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use SuperKernel\Composer\ClassMap\ClassMapBuilder;
use SuperKernel\Composer\Enum\PackageType;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Tokenizer\ClassNameParser;
use Throwable;

use function realpath;

final class ClassMapBuilderTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testOnlyTheRootTypeIncludesDevelopmentClasses(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $autoload = [
                'classmap' => [
                    'src',
                ],
            ];
            $autoloadDev = [
                'classmap' => [
                    'tests',
                ],
            ];
            $this->writeJson($root . '/composer.json', [
                'name'         => 'acme/application',
                'type'         => 'library',
                'autoload'     => $autoload,
                'autoload-dev' => $autoloadDev,
            ]);
            $locked = [];

            foreach ([
                'project',
                'root',
            ] as $type) {
                $locked[] = [
                    'name'         => 'acme/' . $type,
                    'version'      => '1.0.0',
                    'type'         => $type,
                    'autoload'     => $autoload,
                    'autoload-dev' => $autoloadDev,
                ];
                $this->writeFile(
                    $root . '/vendor/acme/' . $type . '/src/Service.source',
                    '<?php namespace Dependency\\' . $type . '; final class Service {}',
                );
                $this->writeFile(
                    $root . '/vendor/acme/' . $type . '/tests/Helper.source',
                    '<?php namespace Dependency\\' . $type . '; final class Helper {}',
                );
            }

            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => $locked,
                'packages-dev' => [],
            ]);
            $this->writeFile($root . '/src/Service.source', '<?php namespace Application; final class Service {}');
            $this->writeFile($root . '/tests/Helper.source', '<?php namespace Application; final class Helper {}');

            $packages = $this->readPackages($root);
            $expected = [
                'acme/application' => [
                    'Application\\Helper'  => realpath($root . '/tests/Helper.source'),
                    'Application\\Service' => realpath($root . '/src/Service.source'),
                ],
                'acme/project' => [
                    'Dependency\\project\\Service' => realpath($root . '/vendor/acme/project/src/Service.source'),
                ],
                'acme/root' => [
                    'Dependency\\root\\Service' => realpath($root . '/vendor/acme/root/src/Service.source'),
                ],
            ];

            self::assertSame(PackageType::Root, $packages->root()->type());
            self::assertSame('library', $packages->root()->composerType());
            self::assertSame(PackageType::Project, $packages->get('acme/project')->type());
            self::assertSame('root', $packages->get('acme/root')->type());

            foreach ($expected as $name => $classes) {
                self::assertSame($classes, $packages->classes($name));
            }

            self::assertSame($expected, new ClassMapBuilder(new ClassNameParser())->build($packages->all()));
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testMapsEveryNamedDeclarationAndSkipsParserRejectedFiles(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $packageRoot = $root . '/vendor/acme/multiple';
            $source = $packageRoot . '/src/Declarations.source';
            $this->writeJson($root . '/composer.json', [
                'name' => 'acme/project',
            ]);
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [
                    [
                    'name'    => 'acme/multiple',
                    'version' => '1.0.0',
                    'dist'    => [
                        'type'      => 'zip',
                        'url'       => 'https://example.test/package.zip',
                        'reference' => 'multiple-reference',
                    ],
                    'autoload' => [
                        'classmap' => [
                            'src',
                        ],
                    ],
                ],
                ],
                'packages-dev' => [],
            ]);
            $this->writeFile($source, <<<'PHP'
                <?php

                namespace Acme\Multiple;

                final class Service {}
                interface Contract {}
                trait Behavior {}
                enum State {}
                PHP);
            $this->writeFile(
                $packageRoot . '/src/Rejected.source',
                '<?php class Broken { public public int $value; }',
            );

            $canonicalSource = realpath($source);
            $this->assertNotFalse($canonicalSource);
            $this->assertSame(
                [
                    'Acme\\Multiple\\Behavior' => $canonicalSource,
                    'Acme\\Multiple\\Contract' => $canonicalSource,
                    'Acme\\Multiple\\Service'  => $canonicalSource,
                    'Acme\\Multiple\\State'    => $canonicalSource,
                ],
                $this->readPackages($root)->classes('acme/multiple'),
            );
        });
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testExplicitClassmapWinsOverPsrDirectoryCandidatesAndAcceptsArbitrarySuffixes(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $packageRoot = $root . '/vendor/acme/polyfill';
            $this->writeJson($root . '/composer.json', [
                'name' => 'acme/project',
            ]);
            $this->writeJson($root . '/composer.lock', [
                'content-hash' => '0123456789abcdef0123456789abcdef',
                'packages'     => [
                    [
                    'name'    => 'acme/polyfill',
                    'version' => '1.0.0',
                    'dist'    => [
                        'type'      => 'zip',
                        'url'       => 'https://example.test/package.zip',
                        'reference' => 'polyfill-reference',
                    ],
                    'autoload' => [
                        'psr-4' => [
                            'Acme\\Polyfill\\' => '',
                        ],
                        'classmap' => [
                            'Resources/stubs',
                        ],
                    ],
                ],
                ],
                'packages-dev' => [],
            ]);
            $this->writeFile(
                $packageRoot . '/Feature.source',
                '<?php namespace Acme\\Polyfill; final class Feature {}',
            );
            $this->writeFile(
                $packageRoot . '/Resources/Deprecated.source',
                '<?php final class Deprecated {}',
            );
            $this->writeFile(
                $packageRoot . '/Resources/stubs/Deprecated.source',
                '<?php final class Deprecated {}',
            );

            $this->assertSame(
                [
                    'Acme\\Polyfill\\Feature' => realpath($packageRoot . '/Feature.source'),
                    'Deprecated'              => realpath($packageRoot . '/Resources/stubs/Deprecated.source'),
                ],
                $this->readPackages($root)->classes('acme/polyfill'),
            );
        });
    }
}
