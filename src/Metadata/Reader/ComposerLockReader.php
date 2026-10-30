<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Reader;

use stdClass;
use SuperKernel\Composer\Constants\ComposerFile;
use SuperKernel\Composer\Constants\JsonType;
use SuperKernel\Composer\Contract\ComposerLockInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Metadata\ComposerLock;
use SuperKernel\Composer\Metadata\Validation\ComposerDocumentValidator;
use SuperKernel\Composer\Metadata\Validation\JsonFieldAssertion;
use SuperKernel\Composer\Metadata\Validation\JsonStructureAssertion;
use SuperKernel\Composer\Metadata\Validation\JsonValueAssertion;

use function get_debug_type;
use function property_exists;
use function sprintf;

/**
 * Reads a `composer.lock` file and returns its validated field view.
 *
 * @internal
 */
final class ComposerLockReader
{
    /** @var list<string> */
    private const array LOCK_REQUIRED_FIELDS = [
        'content-hash',
        'packages',
        'packages-dev',
    ];

    /** @var list<string> */
    private const array LOCK_STRING_FIELDS = [
        'content-hash',
        'minimum-stability',
        'plugin-api-version',
    ];

    /** @var list<string> */
    private const array LOCK_BOOLEAN_FIELDS = [
        'prefer-stable',
        'prefer-lowest',
    ];

    /** @var list<string> */
    private const array LOCK_ARRAY_FIELDS = [
        '_readme',
        'packages',
        'packages-dev',
        'aliases',
    ];

    /** @var list<string> */
    private const array LOCK_OBJECT_FIELDS = [
        'stability-flags',
        'platform',
        'platform-dev',
        'platform-overrides',
    ];

    /** @var list<string> */
    private const array LOCK_PLATFORM_FIELDS = [
        'platform',
        'platform-dev',
        'platform-overrides',
    ];

    /** @var list<string> */
    private const array ALIAS_REQUIRED_FIELDS = [
        'package',
        'version',
        'alias',
        'alias_normalized',
    ];

    /**
     * @throws ComposerFileException When the path does not name composer.lock or the file is unreadable or invalid
     */
    public static function read(string $path): ComposerLockInterface
    {
        ComposerFileDecoder::assertFilename($path, ComposerFile::Lock);
        $document = ComposerFileDecoder::decodeObject($path);
        $context = sprintf('composer.lock at %s', $path);

        foreach (self::LOCK_REQUIRED_FIELDS as $field) {
            if (!property_exists($document, $field)) {
                throw new ComposerFileException(sprintf('%s must declare field "%s".', $context, $field));
            }
        }

        JsonStructureAssertion::assertFieldsHaveType($document, self::LOCK_STRING_FIELDS, JsonType::String, $context);
        JsonStructureAssertion::assertFieldsHaveType($document, self::LOCK_BOOLEAN_FIELDS, JsonType::Boolean, $context);
        JsonStructureAssertion::assertFieldsHaveType($document, self::LOCK_ARRAY_FIELDS, JsonType::Array, $context);

        // Composer serializes some empty lock-file object maps as JSON arrays.
        foreach (self::LOCK_OBJECT_FIELDS as $field) {
            if (!property_exists($document, $field)) {
                continue;
            }

            $value = $document->{$field};

            if ([] === $value || $value instanceof stdClass) {
                continue;
            }

            throw new ComposerFileException(sprintf(
                '%s field "%s" must be object; %s given.',
                $context,
                $field,
                JsonType::of($value)?->value ?? get_debug_type($value),
            ));
        }

        JsonFieldAssertion::assertStringListField($document, '_readme', $context);

        foreach ([
            'packages',
            'packages-dev',
        ] as $field) {
            $packagesContext = sprintf('%s field "%s"', $context, $field);

            foreach ($document->{$field} as $index => $package) {
                $packageContext = sprintf('%s[%d]', $packagesContext, $index);
                JsonValueAssertion::assertValueType($package, JsonType::Object, $packageContext);
                JsonFieldAssertion::assertRequiredStringFields($package, [
                    'name',
                    'version',
                ], $packageContext);
                ComposerDocumentValidator::validate($package, $packageContext);
            }
        }

        if (property_exists($document, 'aliases')) {
            foreach ($document->aliases as $index => $alias) {
                $aliasContext = sprintf('%s field "aliases"[%d]', $context, $index);
                JsonValueAssertion::assertValueType($alias, JsonType::Object, $aliasContext);
                JsonFieldAssertion::assertRequiredStringFields($alias, self::ALIAS_REQUIRED_FIELDS, $aliasContext);
            }
        }

        JsonStructureAssertion::assertObjectValueTypeField($document, 'stability-flags', JsonType::Integer, $context);

        foreach (self::LOCK_PLATFORM_FIELDS as $field) {
            JsonStructureAssertion::assertObjectValueTypeField($document, $field, JsonType::String, $context);
        }

        return new ComposerLock(JsonDocumentNormalizer::normalize($document));
    }
}
