<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Validation;

use SuperKernel\Composer\Constants\JsonType;
use SuperKernel\Composer\Exception\ComposerFileException;

use function get_debug_type;
use function sprintf;

/**
 * Asserts that one decoded JSON value has the expected JSON type.
 *
 * @internal
 */
final class JsonValueAssertion
{
    /**
     * @throws ComposerFileException When the value does not have the required JSON type
     */
    public static function assertValueType(mixed $value, JsonType $expectedType, string $context): void
    {
        $actualType = JsonType::of($value);

        if ($expectedType !== $actualType) {
            throw new ComposerFileException(sprintf(
                '%s must be %s; %s given.',
                $context,
                $expectedType->value,
                $actualType?->value ?? get_debug_type($value),
            ));
        }
    }
}
