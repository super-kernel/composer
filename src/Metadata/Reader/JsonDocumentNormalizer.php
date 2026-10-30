<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Reader;

use stdClass;

use function array_map;
use function get_object_vars;
use function is_array;

/**
 * Converts decoded JSON objects, including nested ones, into native associative arrays.
 *
 * @internal
 */
final class JsonDocumentNormalizer
{
    /**
     * @param stdClass|array<array-key, mixed> $value Decoded JSON object or array
     *
     * @return array<array-key, mixed> Entries in their original order, with every nested object converted to an array
     */
    public static function normalize(stdClass|array $value): array
    {
        return array_map(
            static fn(mixed $item): mixed => $item instanceof stdClass || is_array($item)
                ? self::normalize($item)
                : $item,
            $value instanceof stdClass ? get_object_vars($value) : $value,
        );
    }
}
