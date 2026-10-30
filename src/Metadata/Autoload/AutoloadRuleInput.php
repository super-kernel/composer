<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Autoload;

use stdClass;
use SuperKernel\Composer\Contract\ComposerAutoloadInterface;

/**
 * Converts a declarative autoload view into the Composer autoload object accepted by AutoloadRuleParser.
 *
 * @internal
 */
final class AutoloadRuleInput
{
    public static function fromAutoload(ComposerAutoloadInterface $autoload): stdClass
    {
        $rules = $autoload->classRules();

        return (object) [
            'psr-0'                 => (object) $rules['psr-0'],
            'psr-4'                 => (object) $rules['psr-4'],
            'classmap'              => $rules['classmap'],
            'exclude-from-classmap' => $rules['exclude-from-classmap'],
            'files'                 => $autoload->files(),
        ];
    }
}
