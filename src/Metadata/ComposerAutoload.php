<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use SuperKernel\Composer\Contract\ComposerAutoloadInterface;

use function array_map;
use function array_unique;
use function array_values;
use function is_string;

final readonly class ComposerAutoload implements ComposerAutoloadInterface
{
    /**
     * @param array<string, mixed> $fields Validated autoload or autoload-dev declarations from ComposerFileReader
     */
    public function __construct(private array $fields)
    {
    }

    public function classRules(): array
    {
        $namespacePaths = static fn($paths): array => is_string($paths)
            ? [
                $paths,
            ]
            : array_values(array_unique($paths));

        return [
            'psr-0'                 => array_map($namespacePaths, $this->fields['psr-0'] ?? []),
            'psr-4'                 => array_map($namespacePaths, $this->fields['psr-4'] ?? []),
            'classmap'              => array_values(array_unique($this->fields['classmap'] ?? [])),
            'exclude-from-classmap' => array_values(array_unique($this->fields['exclude-from-classmap'] ?? [])),
        ];
    }

    public function files(): array
    {
        return array_values(array_unique($this->fields['files'] ?? []));
    }
}
