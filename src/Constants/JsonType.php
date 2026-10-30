<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Constants;

use stdClass;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Names the JSON value types that Composer file validation distinguishes in decoded documents.
 *
 * @internal
 */
enum JsonType: string
{
    case Object = 'object';

    case Array = 'array';

    case Boolean = 'boolean';

    case Integer = 'integer';

    case Number = 'number';

    case String = 'string';

    case Null = 'null';

    /**
     * @return self|null The JSON type of a decoded value, or null for a value that JSON decoding cannot produce
     */
    public static function of(mixed $value): ?self
    {
        return match (true) {
            $value instanceof stdClass => self::Object,
            is_array($value)           => self::Array,
            is_bool($value)            => self::Boolean,
            is_int($value)             => self::Integer,
            is_float($value)           => self::Number,
            is_string($value)          => self::String,
            null === $value            => self::Null,
            default                    => null,
        };
    }
}
