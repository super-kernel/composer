<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Model;

use InvalidArgumentException;
use SuperKernel\Composer\Contract\ScanRuleInterface;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;

use function array_any;
use function array_filter;
use function array_push;
use function array_unique;
use function array_values;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function ltrim;
use function preg_match;
use function preg_quote;
use function sort;
use function str_contains;
use function str_replace;
use function strtr;
use function trim;
use function usort;

use const SORT_STRING;

final readonly class ScanRuleSet implements ScanRuleSetInterface
{
    /** @var list<ScanRuleInterface> */
    private array $inclusions;

    /** @var list<string> */
    private array $exclusions;

    /**
     * @param list<ScanRuleInterface> $inclusions
     * @param list<string>            $exclusions
     *
     * @throws InvalidArgumentException When an inclusion or exclusion has an invalid runtime type
     */
    public function __construct(array $inclusions = [], array $exclusions = [])
    {
        foreach ($inclusions as $inclusion) {
            if (!$inclusion instanceof ScanRuleInterface) {
                throw new InvalidArgumentException('Every scan inclusion must implement ScanRuleInterface.');
            }
        }

        usort(
            $inclusions,
            static fn(ScanRuleInterface $left, ScanRuleInterface $right): int => [
                    $left->source()->value,
                    $left->namespacePrefix() ?? '',
                    $left->relativePath(),
                    $left->kind()->value,
                ] <=> [
                    $right->source()->value,
                    $right->namespacePrefix() ?? '',
                    $right->relativePath(),
                    $right->kind()->value,
                ],
        );

        $uniqueInclusions = [];

        foreach ($inclusions as $inclusion) {
            $key = implode("\0", [
                $inclusion->source()->value,
                $inclusion->namespacePrefix() ?? '',
                $inclusion->relativePath(),
                $inclusion->kind()->value,
            ]);
            $uniqueInclusions[$key] = $inclusion;
        }

        foreach ($exclusions as $exclusion) {
            if (!is_string($exclusion)) {
                throw new InvalidArgumentException('Every scan exclusion must be a string.');
            }
        }

        $exclusions = array_values(array_unique($exclusions));
        sort($exclusions, SORT_STRING);

        $this->inclusions = array_values($uniqueInclusions);
        $this->exclusions = $exclusions;
    }

    public static function empty(): ScanRuleSetInterface
    {
        return new self();
    }

    /**
     * Merges, sorts, and deduplicates any number of scan-rule sets.
     */
    public static function merge(ScanRuleSetInterface ...$sets): ScanRuleSetInterface
    {
        $inclusions = [];
        $exclusions = [];

        foreach ($sets as $set) {
            array_push($inclusions, ...$set->inclusions());
            array_push($exclusions, ...$set->exclusions());
        }

        return new self($inclusions, $exclusions);
    }

    public function inclusions(): array
    {
        return $this->inclusions;
    }

    public function exclusions(): array
    {
        return $this->exclusions;
    }

    public function isExcluded(string $packageRelativePath): bool
    {
        if (str_contains($packageRelativePath, "\0")) {
            return false;
        }

        $path = ltrim(str_replace('\\', '/', $packageRelativePath), '/');
        $segments = explode('/', $path);

        if (in_array('..', $segments, true)) {
            return false;
        }

        $path = implode('/', array_values(array_filter(
            $segments,
            static fn(string $segment): bool => '' !== $segment && '.' !== $segment,
        )));

        return array_any(
            $this->exclusions,
            fn(string $pattern): bool => 1 === preg_match(self::exclusionRegex($pattern), $path),
        );
    }

    private static function exclusionRegex(string $pattern): string
    {
        $pattern = trim($pattern, '/');
        $regex = preg_quote($pattern, '~');
        $regex = strtr($regex, [
            '\\*\\*' => '.+?',
            '\\*'    => '[^/]+?',
        ]);

        return '~\\A' . $regex . '(?:\\z|/)~';
    }
}
