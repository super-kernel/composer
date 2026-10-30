<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Validation;

use stdClass;
use SuperKernel\Composer\Constants\JsonType;
use SuperKernel\Composer\Exception\ComposerFileException;

use function property_exists;
use function sprintf;

/**
 * Asserts required string fields and optional string-list fields of decoded JSON objects.
 *
 * @internal
 */
final class JsonFieldAssertion
{
    /**
     * @param list<string> $fields
     *
     * @throws ComposerFileException When required object fields are missing or are not strings
     */
    public static function assertRequiredStringFields(stdClass $object, array $fields, string $context): void
    {
        foreach ($fields as $field) {
            if (!property_exists($object, $field)) {
                throw new ComposerFileException(sprintf('%s must declare field "%s".', $context, $field));
            }
        }

        JsonStructureAssertion::assertFieldsHaveType($object, $fields, JsonType::String, $context);
    }

    /**
     * @throws ComposerFileException When a present field is not a list of strings
     */
    public static function assertStringListField(stdClass $document, string $field, string $context): void
    {
        if (!property_exists($document, $field)) {
            return;
        }

        JsonStructureAssertion::assertStringList($document->{$field}, sprintf('%s field "%s"', $context, $field));
    }
}
