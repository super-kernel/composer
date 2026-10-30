<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Autoload;

use SuperKernel\Composer\Contract\ScanRuleInterface;
use SuperKernel\Composer\Contract\ScanRuleSetInterface;

use function array_push;

/**
 * Collects the inclusions and exclusions of scan-rule sets in argument order.
 *
 * @internal
 */
final class ScanRuleCollector
{
    /**
     * @return array{list<ScanRuleInterface>, list<string>} Inclusions and exclusions, not yet sorted or deduplicated
     */
    public static function collect(ScanRuleSetInterface ...$sets): array
    {
        $inclusions = [];
        $exclusions = [];

        foreach ($sets as $set) {
            array_push($inclusions, ...$set->inclusions());
            array_push($exclusions, ...$set->exclusions());
        }

        return [
            $inclusions,
            $exclusions,
        ];
    }
}
