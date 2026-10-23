<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

/**
 * Describes native package-relative autoload declarations without resolving or executing filesystem entries.
 */
interface ComposerAutoloadInterface
{
    /**
     * Normalizes PSR path strings into lists and separates class declarations from file autoloading.
     *
     * Prefixes, path spelling, and declaration order remain significant. Missing collections are empty and duplicate
     * paths are removed within each collection, preserving the first occurrence. Exclusion patterns remain intact.
     *
     * @return array{
     *     'psr-0': array<string, list<string>>,
     *     'psr-4': array<string, list<string>>,
     *     classmap: list<string>,
     *     'exclude-from-classmap': list<string>
     * }
     */
    public function classRules(): array;

    /**
     * @return list<string> Deduplicated package-relative autoload.files paths in declaration order, without execution
     */
    public function files(): array;
}
