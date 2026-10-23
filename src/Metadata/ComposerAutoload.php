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
        return [
            'psr-0'                 => $this->namespaceRules('psr-0'),
            'psr-4'                 => $this->namespaceRules('psr-4'),
            'classmap'              => array_values(array_unique($this->fields['classmap'] ?? [])),
            'exclude-from-classmap' => array_values(array_unique($this->fields['exclude-from-classmap'] ?? [])),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function namespaceRules(string $source): array
    {
        return array_map(function ($paths) {
            return is_string($paths)
                ? [
                    $paths,
                ]
                : array_values(array_unique($paths));
        }, $this->fields[$source] ?? []);
    }

    public function files(): array
    {
        return array_values(array_unique($this->fields['files'] ?? []));
    }
}
