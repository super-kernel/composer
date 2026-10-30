<?php

declare(strict_types=1);

namespace SuperKernel\Composer\ClassMap;

use SuperKernel\Composer\Constants\AutoloadScope;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;
use SuperKernel\Tokenizer\Contract\ClassNameParserInterface;

/**
 * Derives deterministic package class maps from validated Composer scan rules.
 */
final readonly class ClassMapBuilder
{
    private PackageClassScanner $scanner;

    public function __construct(ClassNameParserInterface $classNameParser)
    {
        $this->scanner = new PackageClassScanner($classNameParser);
    }

    /**
     * @param list<PackageInterface> $packages
     *
     * @return array<string, array<class-string, non-empty-string>> Class maps keyed by package name
     */
    public function build(array $packages): array
    {
        $classMaps = [];

        foreach ($packages as $package) {
            $scanRules = $package->scanRulesFor(AutoloadScope::Development);
            $classMaps[$package->name()] = $this->scanner->scan($package, $scanRules);
        }

        return $classMaps;
    }

    /**
     * @return array<class-string, non-empty-string> Sorted class map
     */
    public function buildPackage(PackageInterface $package, ?ScanRuleSetInterface $scanRules = null): array
    {
        return $this->scanner->scan($package, $scanRules);
    }
}
