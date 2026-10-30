<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Validation;

use stdClass;
use SuperKernel\Composer\Constants\JsonType;
use SuperKernel\Composer\Exception\ComposerFileException;

use function array_column;
use function array_keys;
use function get_debug_type;
use function get_object_vars;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function property_exists;
use function sprintf;

/**
 * Validates the structure of official composer.json fields in a root manifest or a lock-file package entry.
 *
 * @internal
 */
final class ComposerDocumentValidator
{
    /** @var list<string> */
    private const array COMPOSER_STRING_FIELDS = [
        'name',
        'description',
        'type',
        'version',
        'version_normalized',
        'notification-url',
        'installation-source',
        'readme',
        'time',
        'homepage',
        'minimum-stability',
        'target-dir',
    ];

    /** @var list<string> */
    private const array COMPOSER_BOOLEAN_FIELDS = [
        'default-branch',
        'prefer-stable',
    ];

    /** @var list<string> */
    private const array COMPOSER_ARRAY_FIELDS = [
        'non-feature-branches',
        'keywords',
        'authors',
        'funding',
        'include-path',
    ];

    /** @var list<string> */
    private const array COMPOSER_OBJECT_FIELDS = [
        'support',
        'source',
        'dist',
        'require',
        'require-dev',
        'replace',
        'conflict',
        'provide',
        'suggest',
        'autoload',
        'autoload-dev',
        'archive',
        'php-ext',
        'config',
        'scripts',
        'scripts-descriptions',
        'scripts-aliases',
        'transport-options',
    ];

    /** @var array<string, list<JsonType>> */
    private const array COMPOSER_UNION_FIELDS = [
        'license' => [
            JsonType::Array,
            JsonType::String,
        ],
        'abandoned' => [
            JsonType::Boolean,
            JsonType::String,
        ],
        '_comment' => [
            JsonType::Array,
            JsonType::String,
        ],
        'repositories' => [
            JsonType::Array,
            JsonType::Object,
        ],
        'bin' => [
            JsonType::Array,
            JsonType::String,
        ],
        'extra' => [
            JsonType::Array,
            JsonType::Object,
        ],
    ];

    /** @var list<string> */
    private const array COMPOSER_LINK_FIELDS = [
        'require',
        'require-dev',
        'replace',
        'conflict',
        'provide',
        'suggest',
    ];

    /** @var list<string> */
    private const array COMPOSER_STRING_LIST_FIELDS = [
        'non-feature-branches',
        'keywords',
        'include-path',
    ];

    /** @var list<string> */
    private const array COMPOSER_STRING_OR_STRING_LIST_FIELDS = [
        'license',
        '_comment',
        'bin',
    ];

    /** @var list<string> */
    private const array AUTOLOAD_NAMESPACE_FIELDS = [
        'psr-0',
        'psr-4',
    ];

    /** @var list<string> */
    private const array AUTOLOAD_PATH_FIELDS = [
        'classmap',
        'files',
        'exclude-from-classmap',
    ];

    /** @var list<string> */
    private const array SUPPORT_STRING_FIELDS = [
        'email',
        'issues',
        'forum',
        'wiki',
        'irc',
        'chat',
        'source',
        'docs',
        'rss',
        'security',
    ];

    /** @var list<string> */
    private const array AUTHOR_FIELDS = [
        'name',
        'email',
        'homepage',
        'role',
    ];

    private const string PACKAGE_NAME_PATTERN = '~^[a-z0-9]([_.-]?[a-z0-9]+)*/'
        . '[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$~D';

    private const string PACKAGE_TYPE_PATTERN = '~^[a-z0-9-]+$~D';

    /**
     * Checks fields in a fixed order, so the first invalid field determines the reported failure.
     *
     * @throws ComposerFileException When an official composer.json field has an invalid structure
     */
    public static function validate(stdClass $document, string $context): void
    {
        JsonStructureAssertion::assertFieldsHaveType(
            $document,
            self::COMPOSER_STRING_FIELDS,
            JsonType::String,
            $context,
        );
        JsonStructureAssertion::assertFieldsHaveType(
            $document,
            self::COMPOSER_BOOLEAN_FIELDS,
            JsonType::Boolean,
            $context,
        );
        JsonStructureAssertion::assertFieldsHaveType($document, self::COMPOSER_ARRAY_FIELDS, JsonType::Array, $context);
        JsonStructureAssertion::assertFieldsHaveType(
            $document,
            self::COMPOSER_OBJECT_FIELDS,
            JsonType::Object,
            $context,
        );

        foreach (self::COMPOSER_UNION_FIELDS as $field => $types) {
            if (!property_exists($document, $field)) {
                continue;
            }

            $actualType = JsonType::of($document->{$field});

            if (!in_array($actualType, $types, true)) {
                throw new ComposerFileException(sprintf(
                    '%s field "%s" must be %s; %s given.',
                    $context,
                    $field,
                    implode(' or ', array_column($types, 'value')),
                    $actualType?->value ?? get_debug_type($document->{$field}),
                ));
            }
        }

        foreach (self::COMPOSER_STRING_LIST_FIELDS as $field) {
            JsonFieldAssertion::assertStringListField($document, $field, $context);
        }

        foreach (self::COMPOSER_STRING_OR_STRING_LIST_FIELDS as $field) {
            if (!property_exists($document, $field) || is_string($document->{$field})) {
                continue;
            }

            JsonStructureAssertion::assertStringList($document->{$field}, sprintf('%s field "%s"', $context, $field));
        }

        foreach (self::COMPOSER_LINK_FIELDS as $field) {
            JsonStructureAssertion::assertObjectValueTypeField($document, $field, JsonType::String, $context);
        }

        if (
            property_exists($document, 'name')
            && 1 !== preg_match(self::PACKAGE_NAME_PATTERN, $document->name)
        ) {
            throw new ComposerFileException(sprintf(
                '%s field "name" must be a lowercase Composer package name.',
                $context,
            ));
        }

        if (
            property_exists($document, 'type')
            && 1 !== preg_match(self::PACKAGE_TYPE_PATTERN, $document->type)
        ) {
            throw new ComposerFileException(sprintf('%s field "type" has an invalid Composer package type.', $context));
        }

        if (
            property_exists($document, 'minimum-stability')
            && !in_array($document->{'minimum-stability'}, [
                'dev',
                'alpha',
                'beta',
                'rc',
                'RC',
                'stable',
            ], true)
        ) {
            throw new ComposerFileException(sprintf(
                '%s field "minimum-stability" has an invalid stability value.',
                $context,
            ));
        }

        if (property_exists($document, 'authors')) {
            foreach ($document->authors as $index => $author) {
                $authorContext = sprintf('%s field "authors"[%d]', $context, $index);
                JsonValueAssertion::assertValueType($author, JsonType::Object, $authorContext);

                if (!property_exists($author, 'name')) {
                    throw new ComposerFileException(sprintf('%s must declare field "name".', $authorContext));
                }

                foreach (array_keys(get_object_vars($author)) as $authorField) {
                    if (!in_array($authorField, self::AUTHOR_FIELDS, true)) {
                        throw new ComposerFileException(
                            sprintf('%s contains unsupported field "%s".', $authorContext, $authorField),
                        );
                    }
                }

                JsonStructureAssertion::assertFieldsHaveType(
                    $author,
                    self::AUTHOR_FIELDS,
                    JsonType::String,
                    $authorContext,
                );
            }
        }

        if (property_exists($document, 'funding')) {
            foreach ($document->funding as $index => $funding) {
                $fundingContext = sprintf('%s field "funding"[%d]', $context, $index);
                JsonValueAssertion::assertValueType($funding, JsonType::Object, $fundingContext);
                JsonStructureAssertion::assertFieldsHaveType($funding, [
                    'type',
                    'url',
                ], JsonType::String, $fundingContext);
            }
        }

        if (property_exists($document, 'support')) {
            JsonStructureAssertion::assertFieldsHaveType(
                $document->support,
                self::SUPPORT_STRING_FIELDS,
                JsonType::String,
                sprintf('%s field "support"', $context),
            );
        }

        if (property_exists($document, 'source')) {
            JsonFieldAssertion::assertRequiredStringFields(
                $document->source,
                [
                    'type',
                    'url',
                    'reference',
                ],
                sprintf('%s field "source"', $context),
            );

            if (property_exists($document->source, 'mirrors')) {
                JsonValueAssertion::assertValueType(
                    $document->source->mirrors,
                    JsonType::Array,
                    sprintf('%s field "source.mirrors"', $context),
                );
            }
        }

        if (property_exists($document, 'dist')) {
            JsonFieldAssertion::assertRequiredStringFields(
                $document->dist,
                [
                    'type',
                    'url',
                ],
                sprintf('%s field "dist"', $context),
            );
            JsonStructureAssertion::assertFieldsHaveType(
                $document->dist,
                [
                    'reference',
                    'shasum',
                ],
                JsonType::String,
                sprintf('%s field "dist"', $context),
            );

            if (property_exists($document->dist, 'mirrors')) {
                JsonValueAssertion::assertValueType(
                    $document->dist->mirrors,
                    JsonType::Array,
                    sprintf('%s field "dist.mirrors"', $context),
                );
            }
        }

        foreach ([
            'autoload',
            'autoload-dev',
        ] as $field) {
            if (!property_exists($document, $field)) {
                continue;
            }

            $autoload = $document->{$field};
            $autoloadContext = sprintf('%s field "%s"', $context, $field);

            foreach (self::AUTOLOAD_NAMESPACE_FIELDS as $namespaceField) {
                if (!property_exists($autoload, $namespaceField)) {
                    continue;
                }

                JsonValueAssertion::assertValueType(
                    $autoload->{$namespaceField},
                    JsonType::Object,
                    sprintf('%s.%s', $autoloadContext, $namespaceField),
                );

                foreach (get_object_vars($autoload->{$namespaceField}) as $prefix => $paths) {
                    $mappingContext = sprintf('%s.%s field "%s"', $autoloadContext, $namespaceField, $prefix);

                    if (is_string($paths)) {
                        continue;
                    }

                    JsonStructureAssertion::assertStringList($paths, $mappingContext);
                }
            }

            foreach (self::AUTOLOAD_PATH_FIELDS as $pathField) {
                if (property_exists($autoload, $pathField)) {
                    JsonStructureAssertion::assertStringList(
                        $autoload->{$pathField},
                        sprintf('%s.%s', $autoloadContext, $pathField),
                    );
                }
            }
        }

        if (property_exists($document, 'archive')) {
            $archiveContext = sprintf('%s field "archive"', $context);
            JsonStructureAssertion::assertFieldsHaveType($document->archive, [
                'name',
            ], JsonType::String, $archiveContext);

            if (property_exists($document->archive, 'exclude')) {
                JsonStructureAssertion::assertStringList(
                    $document->archive->exclude,
                    sprintf('%s.exclude', $archiveContext),
                );
            }
        }

        if (property_exists($document, 'scripts')) {
            foreach (get_object_vars($document->scripts) as $name => $script) {
                if (is_string($script)) {
                    continue;
                }

                JsonStructureAssertion::assertStringList($script, sprintf('%s field "scripts.%s"', $context, $name));
            }
        }

        JsonStructureAssertion::assertObjectValueTypeField(
            $document,
            'scripts-descriptions',
            JsonType::String,
            $context,
        );

        if (property_exists($document, 'scripts-aliases')) {
            foreach (get_object_vars($document->{'scripts-aliases'}) as $name => $aliases) {
                JsonStructureAssertion::assertStringList(
                    $aliases,
                    sprintf('%s field "scripts-aliases.%s"', $context, $name),
                );
            }
        }
    }
}
