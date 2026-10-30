<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Autoload;

use stdClass;
use SuperKernel\Composer\Exception\AutoloadRuleException;

use function array_is_list;
use function array_keys;
use function is_array;
use function is_file;
use function is_string;
use function property_exists;

/**
 * Resolves Composer file-autoload declarations without turning them into class-map scan rules.
 *
 * @internal
 */
final class AutoloadFileResolver
{
    /**
     * @param string|null $packageRoot Canonical package directory, or null when the package is unlocated
     *
     * @return list<non-empty-string> Canonical existing files in declaration order
     *
     * @throws AutoloadRuleException When a declaration is malformed, unsafe, or resolves outside the package
     */
    public static function resolve(?stdClass $autoload, ?string $packageRoot): array
    {
        if (null === $autoload || !property_exists($autoload, 'files')) {
            return [];
        }

        $files = $autoload->files;

        if (!is_array($files) || !array_is_list($files)) {
            throw new AutoloadRuleException('autoload.files must be a string list.');
        }

        $resolvedFiles = [];

        foreach ($files as $file) {
            if (!is_string($file)) {
                throw new AutoloadRuleException('autoload.files must contain only strings.');
            }

            $relativePath = AutoloadPath::normalize($file);

            if (null === $packageRoot) {
                continue;
            }

            $resolved = AutoloadPath::resolveExisting($packageRoot, $relativePath);

            if (null !== $resolved && is_file($resolved)) {
                $resolvedFiles[$resolved] = true;
            }
        }

        return array_keys($resolvedFiles);
    }
}
