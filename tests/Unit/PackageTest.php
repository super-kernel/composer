<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use SuperKernel\Composer\Enum\AutoloadScope;
use SuperKernel\Composer\Enum\PackageType;
use SuperKernel\Composer\Enum\ScanRuleKind;
use SuperKernel\Composer\Enum\ScanRuleSource;
use SuperKernel\Composer\Model\Package;
use SuperKernel\Composer\Model\ScanRule;
use SuperKernel\Composer\Model\ScanRuleSet;
use SuperKernel\Composer\Tests\Support\TestCase;

final class PackageTest extends TestCase
{
    public function testRootUsesItsTypeToSelectDevelopmentRulesAndKeepsTheDeclaredComposerType(): void
    {
        $production = new ScanRuleSet([
            new ScanRule('src', ScanRuleSource::Classmap, ScanRuleKind::Directory, null),
        ]);
        $development = ScanRuleSet::merge($production, new ScanRuleSet([
            new ScanRule('tests', ScanRuleSource::Classmap, ScanRuleKind::Directory, null),
        ]));
        $package = new Package(
            'acme/application',
            null,
            null,
            PackageType::Root,
            false,
            '/project',
            $production,
            developmentScanRules: $development,
            composerType: 'custom-project',
        );

        self::assertSame(PackageType::Root, $package->type());
        self::assertSame('custom-project', $package->composerType());
        self::assertSame('/project', $package->locatedPath());
        self::assertSame($production, $package->scanRules());
        self::assertSame($production, $package->scanRulesFor(AutoloadScope::Production));
        self::assertSame($development, $package->scanRulesFor(AutoloadScope::Development));
    }

    #[DataProvider('lockedTypes')]
    public function testLockedTypesNeverEnableDevelopmentRules(PackageType|string $type, string $declared): void
    {
        $production = ScanRuleSet::empty();
        $package = new Package(
            'acme/dependency',
            '1.0.0',
            null,
            $declared,
            true,
            null,
            $production,
            developmentScanRules: new ScanRuleSet([
                new ScanRule('tests', ScanRuleSource::Classmap, ScanRuleKind::Directory, null),
            ]),
        );

        self::assertSame($type, $package->type());
        self::assertSame($declared, $package->composerType());
        self::assertSame($production, $package->scanRulesFor(AutoloadScope::Production));
        self::assertSame($production, $package->scanRulesFor(AutoloadScope::Development));
    }

    /** @return iterable<string, array{PackageType|string, string}> */
    public static function lockedTypes(): iterable
    {
        yield 'library' => [
            PackageType::Library,
            'library',
        ];
        yield 'project' => [
            PackageType::Project,
            'project',
        ];
        yield 'custom root' => [
            'root',
            'root',
        ];
        yield 'custom installer' => [
            'custom-installer',
            'custom-installer',
        ];
    }

    #[DataProvider('invalidRootState')]
    public function testRejectsInvalidRootState(
        ?string $path,
        ?string $reference,
        bool    $development,
    ): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'The root package must have a project path and no lock-derived state.',
        );

        new Package(
            'acme/application',
            null,
            $reference,
            PackageType::Root,
            $development,
            $path,
            ScanRuleSet::empty(),
        );
    }

    /** @return iterable<string, array{string|null, string|null, bool}> */
    public static function invalidRootState(): iterable
    {
        yield 'missing path' => [
            null,
            null,
            false,
        ];
        yield 'lock reference' => [
            '/project',
            'reference',
            false,
        ];
        yield 'lock development membership' => [
            '/project',
            null,
            true,
        ];
    }
}
