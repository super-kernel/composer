<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use InvalidArgumentException;
use SuperKernel\Composer\Constants\PackageType;
use SuperKernel\Composer\Manager\PackageManager;
use SuperKernel\Composer\Model\EnvironmentRequirement;
use SuperKernel\Composer\Model\Package;
use SuperKernel\Composer\Model\ScanRuleSet;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\EnvironmentRequirementInterface;
use SuperKernel\Contract\PackageManagerInterface;
use Throwable;

use function spl_autoload_functions;

final class PackageManagerTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRuntimeManagerAcceptsPreparedPackagesWithoutAnInstalledWorkspace(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $rules = ScanRuleSet::empty();
            $rootPackage = new Package(
                'fixture/application',
                null,
                null,
                PackageType::Root,
                false,
                $root,
                $rules,
                composerType: 'project',
            );
            $loaders = spl_autoload_functions();
            $manager = new PackageManager(
                $rootPackage,
                [],
                [
                    'fixture/application' => [
                        'Fixture\\PreparedClass' => $root . '/PreparedClass.php',
                    ],
                ],
                [
                    $root . '/functions.php',
                ],
            );

            self::assertSame([
                $rootPackage,
            ], $manager->all());
            self::assertSame([], $manager->packages());
            self::assertSame([], $manager->environmentRequirements());
            self::assertFalse($manager->hasEnvironmentRequirements());
            self::assertSame($rootPackage, $manager->packageForClass('Fixture\\PreparedClass'));
            self::assertSame([
                'Fixture\\PreparedClass' => $root . '/PreparedClass.php',
            ], $manager->classes('FIXTURE/APPLICATION'));
            self::assertSame($manager->classes('fixture/application'), $manager->allClasses());
            self::assertSame([
                $root . '/functions.php',
            ], $manager->autoloadFiles());
            self::assertDirectoryDoesNotExist($root . '/vendor');
            self::assertFileDoesNotExist($root . '/composer.json');
            self::assertSame($loaders, spl_autoload_functions());
        });
    }

    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testSharedManagerExposesPackageQueriesAndEnvironmentRequirements(): void
    {
        $rootPhp = new EnvironmentRequirement('php', '>=8.4', 'acme/root', false);
        $rootDevExtension = new EnvironmentRequirement('ext-xdebug', '*', 'acme/root', true);
        $dependencyPhp = new EnvironmentRequirement('php', '^8.4', 'acme/alpha', false);
        $root = new Package(
            'acme/root',
            null,
            null,
            PackageType::Root,
            false,
            '/project',
            ScanRuleSet::empty(),
            environmentRequirements: [
                $rootPhp,
                $rootDevExtension,
            ],
            composerType: 'project',
        );
        $alpha = new Package(
            'acme/alpha',
            '1.0.0',
            'alpha-reference',
            PackageType::Project,
            false,
            null,
            ScanRuleSet::empty(),
            environmentRequirements: [
                $dependencyPhp,
            ],
        );
        $zeta = $this->package('acme/zeta', 'zeta-reference');
        $manager = new PackageManager($root, [
            $zeta,
            $alpha,
        ]);

        self::assertInstanceOf(PackageManagerInterface::class, $manager);
        self::assertSame($root, $manager->root());
        self::assertSame([
            $root,
            $alpha,
            $zeta,
        ], $manager->all());
        self::assertSame([
            $alpha,
            $zeta,
        ], $manager->packages());
        self::assertSame($root, $manager->get('ACME/ROOT'));
        self::assertSame($alpha, $manager->get('ACME/ALPHA'));
        self::assertNull($manager->get('acme/missing'));
        self::assertSame([
            $root,
            $alpha,
        ], $manager->ofComposerType('project'));
        self::assertSame([
            $zeta,
        ], $manager->ofComposerType('library'));
        self::assertSame([], $manager->ofComposerType('Project'));
        self::assertTrue($manager->hasEnvironmentRequirements());
        self::assertSame([
            $rootDevExtension,
            $dependencyPhp,
            $rootPhp,
        ], $manager->environmentRequirements());

        foreach ($manager->environmentRequirements() as $requirement) {
            self::assertInstanceOf(EnvironmentRequirementInterface::class, $requirement);
        }
    }

    public function testKeepsPerPackageClassGraphsAndUsesRootFirstForTheAggregateMap(): void
    {
        $manager = new PackageManager(
            $this->rootPackage(),
            [
                $this->package('acme/dependency', 'dependency-reference'),
            ],
            [
                'ACME/ROOT' => [
                    'Acme\\RootClass' => '/project/src/RootClass.php',
                    'Shared\\Symbol'  => '/project/src/Shared.php',
                ],
                'ACME/DEPENDENCY' => [
                    'Acme\\DependencyClass' => '/project/vendor/acme/dependency/src/DependencyClass.php',
                    'Shared\\Symbol'        => '/project/vendor/acme/dependency/src/Shared.php',
                ],
            ],
            [
                '/project/vendor/acme/dependency/functions.php',
                '/project/functions.php',
                '/project/vendor/acme/dependency/functions.php',
            ],
        );

        $this->assertSame(
            [
                'Acme\\DependencyClass' => '/project/vendor/acme/dependency/src/DependencyClass.php',
                'Shared\\Symbol'        => '/project/vendor/acme/dependency/src/Shared.php',
            ],
            $manager->classes('ACME/DEPENDENCY'),
        );
        $this->assertSame(
            [
                'Acme\\DependencyClass' => '/project/vendor/acme/dependency/src/DependencyClass.php',
                'Acme\\RootClass'       => '/project/src/RootClass.php',
                'Shared\\Symbol'        => '/project/src/Shared.php',
            ],
            $manager->allClasses(),
        );
        $this->assertNull($manager->classes('acme/missing'));
        $this->assertSame('acme/root', $manager->packageForClass('Shared\\Symbol')?->name());
        $this->assertSame(
            'acme/dependency',
            $manager->packageForClass('Acme\\DependencyClass')?->name(),
        );
        $this->assertNull($manager->packageForClass('Acme\\Missing'));
        $this->assertSame([
            '/project/vendor/acme/dependency/functions.php',
            '/project/functions.php',
        ], $manager->autoloadFiles());
        $this->assertSame($manager->autoloadFiles(), $manager->withClassMaps([])->autoloadFiles());
    }

    public function testRejectsClassMapsForUnknownPackages(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            fn() => new PackageManager(
                $this->rootPackage(),
                [],
                [
                    'acme/unknown' => [
                        'Acme\\Unknown' => '/tmp/Unknown.php',
                    ],
                ],
            ),
            messageContains: 'managed package name',
        );
    }

    public function testRejectsDuplicatePackageNamesEvenWhenTheSameObjectIsRepeated(): void
    {
        $package = $this->package('acme/dependency', 'dependency-reference');

        $this->assertThrows(
            InvalidArgumentException::class,
            fn() => new PackageManager($this->rootPackage(), [
                $package,
                $package,
            ]),
            messageContains: 'occurs more than once',
        );
    }

    /**
     * Deliberately passes an invalid map shape to exercise runtime validation.
     *
     * @noinspection PhpParamsInspection
     */
    public function testRejectsANonArrayPackageClassMap(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            fn() => new PackageManager(
                $this->rootPackage(),
                [],
                [
                    'acme/root' => 'invalid',
                ],
            ),
            messageContains: 'must be an array',
        );
    }

    public function testRejectsALockedPackageAsTheRoot(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            fn() => new PackageManager($this->package('acme/root', null), []),
            messageContains: 'root package must have the Root package type',
        );
    }

    public function testRejectsRootPackagesInTheLockedPackageList(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            fn() => new PackageManager($this->rootPackage(), [
                $this->rootPackage(),
            ]),
            messageContains: 'Lock-file packages must not have the Root package type',
        );
    }

    private function rootPackage(): Package
    {
        $rules = ScanRuleSet::empty();

        return new Package(
            'acme/root',
            null,
            null,
            PackageType::Root,
            false,
            '/project',
            $rules,
            composerType: 'project',
        );
    }

    private function package(string $name, ?string $reference): Package
    {
        return new Package(
            $name,
            '1.0.0',
            $reference,
            'library',
            false,
            null,
            ScanRuleSet::empty(),
        );
    }
}
