<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Validation;

use stdClass;
use SuperKernel\Composer\Constants\JsonType;
use SuperKernel\Composer\Exception\ComposerFileException;

use function get_object_vars;
use function property_exists;
use function sprintf;

/**
 * Asserts the JSON types of present object fields, string lists, and object-map values.
 *
 * @internal
 */
final class JsonStructureAssertion
{
    /**
     * @param list<string> $fields
     *
     * @throws ComposerFileException When a present field does not have the required JSON type
     */
    public static function assertFieldsHaveType(
        stdClass $document,
        array    $fields,
        JsonType $expectedType,
        string   $context,
    ): void
    {
        foreach ($fields as $field) {
            if (!property_exists($document, $field)) {
                continue;
            }

            JsonValueAssertion::assertValueType(
                $document->{$field},
                $expectedType,
                sprintf('%s field "%s"', $context, $field),
            );
        }
    }

    /**
     * @throws ComposerFileException When the value is not a list of strings
     */
    public static function assertStringList(mixed $value, string $context): void
    {
        JsonValueAssertion::assertValueType($value, JsonType::Array, $context);

        foreach ($value as $index => $item) {
            JsonValueAssertion::assertValueType($item, JsonType::String, sprintf('%s[%d]', $context, $index));
        }
    }

    /**
     * Accepts an empty JSON array in place of an empty object map.
     *
     * @throws ComposerFileException When a present object field contains a value of the wrong JSON type
     */
    public static function assertObjectValueTypeField(
        stdClass $document,
        string   $field,
        JsonType $expectedType,
        string   $context,
    ): void
    {
        if (!property_exists($document, $field)) {
            return;
        }

        if ([] === $document->{$field}) {
            return;
        }

        JsonValueAssertion::assertValueType(
            $document->{$field},
            JsonType::Object,
            sprintf('%s field "%s"', $context, $field),
        );

        foreach (get_object_vars($document->{$field}) as $name => $value) {
            JsonValueAssertion::assertValueType(
                $value,
                $expectedType,
                sprintf('%s field "%s.%s"', $context, $field, $name),
            );
        }
    }
}
