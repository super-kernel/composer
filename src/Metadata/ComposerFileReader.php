<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use JsonException;
use stdClass;
use SuperKernel\Composer\Contract\ComposerJsonInterface;
use SuperKernel\Composer\Contract\ComposerLockInterface;
use SuperKernel\Composer\Exception\ComposerFileException;

use function array_keys;
use function array_map;
use function basename;
use function file_get_contents;
use function get_debug_type;
use function get_object_vars;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_file;
use function is_float;
use function is_int;
use function is_readable;
use function is_string;
use function json_decode;
use function preg_match;
use function property_exists;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Reads and structurally validates composer.json and `composer.lock` files without network or Composer runtime access.
 */
final class ComposerFileReader
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
    private const array AUTHOR_FIELDS = [
        'name',
        'email',
        'homepage',
        'role',
    ];

    /** @var list<string> */
    private const array ALIAS_REQUIRED_FIELDS = [
        'package',
        'version',
        'alias',
        'alias_normalized',
    ];

    private const string PACKAGE_NAME_PATTERN = '~^[a-z0-9]([_.-]?[a-z0-9]+)*/'
        . '[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$~D';

    private const string PACKAGE_TYPE_PATTERN = '~^[a-z0-9-]+$~D';

    /**
     * Reports whether a supported Composer file is readable, valid JSON, and structurally valid.
     */
    public static function validate(string $path): bool
    {
        try {
            self::read($path);
        } catch (ComposerFileException) {
            return false;
        }

        return true;
    }

    /**
     * Reads either supported Composer file according to its exact basename.
     *
     * @throws ComposerFileException When the path is unsupported or the file is unreadable or invalid
     */
    public static function read(string $path): ComposerJsonInterface|ComposerLockInterface
    {
        return match (basename($path)) {
            'composer.json' => self::readComposerJson($path),
            'composer.lock' => self::readComposerLock($path),
            default         => throw new ComposerFileException(sprintf(
                'Expected a composer.json or composer.lock path, got %s.',
                $path,
            )),
        };
    }

    /**
     * Reads a composer.json file and returns its validated field view.
     *
     * @throws ComposerFileException When the path does not name composer.json or the file is unreadable or invalid
     */
    public static function readComposerJson(string $path): ComposerJsonInterface
    {
        self::assertFilename($path, 'composer.json');
        $document = self::decodeObject($path);
        self::validateComposerDocument($document, sprintf('composer.json at %s', $path));

        return new ComposerJson(self::normalizeObject($document));
    }

    /**
     * @throws ComposerFileException When the path does not have the expected basename
     */
    private static function assertFilename(string $path, string $expected): void
    {
        if ($expected !== basename($path)) {
            throw new ComposerFileException(sprintf('Expected %s path, got %s.', $expected, $path));
        }
    }

    /**
     * @throws ComposerFileException When the file is unavailable, unreadable, malformed, or not a JSON object
     */
    private static function decodeObject(string $path): stdClass
    {
        if (!is_file($path)) {
            throw new ComposerFileException(sprintf('Composer file is unavailable or not a regular file: %s.', $path));
        }

        if (!is_readable($path)) {
            throw new ComposerFileException(sprintf('Composer file is not readable: %s.', $path));
        }

        $contents = @file_get_contents($path);

        if (false === $contents) {
            throw new ComposerFileException(sprintf('Unable to read Composer file: %s.', $path));
        }

        try {
            $document = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ComposerFileException(
                sprintf('Invalid JSON in %s: %s', $path, $exception->getMessage()),
                previous: $exception,
            );
        }

        if (!$document instanceof stdClass) {
            throw new ComposerFileException(sprintf('Composer file must contain a JSON object: %s.', $path));
        }

        return $document;
    }

    /**
     * @throws ComposerFileException When an official composer.json field has an invalid structure
     */
    private static function validateComposerDocument(stdClass $document, string $context): void
    {
        self::assertFieldsHaveType($document, self::COMPOSER_STRING_FIELDS, 'string', $context);
        self::assertFieldsHaveType($document, self::COMPOSER_BOOLEAN_FIELDS, 'boolean', $context);
        self::assertFieldsHaveType($document, self::COMPOSER_ARRAY_FIELDS, 'array', $context);
        self::assertFieldsHaveType($document, self::COMPOSER_OBJECT_FIELDS, 'object', $context);

        self::assertUnionField($document, 'license', [
            'array',
            'string',
        ], $context);
        self::assertUnionField($document, 'abandoned', [
            'boolean',
            'string',
        ], $context);
        self::assertUnionField($document, '_comment', [
            'array',
            'string',
        ], $context);
        self::assertUnionField($document, 'repositories', [
            'array',
            'object',
        ], $context);
        self::assertUnionField($document, 'bin', [
            'array',
            'string',
        ], $context);
        self::assertUnionField($document, 'extra', [
            'array',
            'object',
        ], $context);

        foreach (self::COMPOSER_STRING_LIST_FIELDS as $field) {
            self::assertStringListField($document, $field, $context);
        }

        self::assertStringOrStringListField($document, 'license', $context);
        self::assertStringOrStringListField($document, '_comment', $context);
        self::assertStringOrStringListField($document, 'bin', $context);

        foreach (self::COMPOSER_LINK_FIELDS as $field) {
            self::assertObjectValueTypeField($document, $field, 'string', $context);
        }

        self::assertPackageName($document, $context);
        self::assertPackageType($document, $context);
        self::assertMinimumStability($document, $context);
        self::assertAuthors($document, $context);
        self::assertFunding($document, $context);
        self::assertSupport($document, $context);
        self::assertSource($document, $context);
        self::assertDist($document, $context);
        self::assertAutoload($document, 'autoload', $context);
        self::assertAutoload($document, 'autoload-dev', $context);
        self::assertArchive($document, $context);
        self::assertScripts($document, $context);
        self::assertObjectValueTypeField($document, 'scripts-descriptions', 'string', $context);
        self::assertScriptsAliases($document, $context);
    }

    /**
     * @param list<string> $fields
     *
     * @throws ComposerFileException When a present field does not have the required JSON type
     */
    private static function assertFieldsHaveType(
        stdClass $document,
        array    $fields,
        string   $expectedType,
        string   $context,
    ): void
    {
        foreach ($fields as $field) {
            if (!property_exists($document, $field)) {
                continue;
            }

            self::assertValueType($document->{$field}, $expectedType, sprintf('%s field "%s"', $context, $field));
        }
    }

    /**
     * @throws ComposerFileException When the value does not have the required JSON type
     */
    private static function assertValueType(mixed $value, string $expectedType, string $context): void
    {
        $actualType = self::jsonType($value);

        if ($expectedType !== $actualType) {
            throw new ComposerFileException(sprintf(
                '%s must be %s; %s given.',
                $context,
                $expectedType,
                $actualType,
            ));
        }
    }

    private static function jsonType(mixed $value): string
    {
        return match (true) {
            $value instanceof stdClass => 'object',
            is_array($value)           => 'array',
            is_bool($value)            => 'boolean',
            is_int($value)             => 'integer',
            is_float($value)           => 'number',
            is_string($value)          => 'string',
            null === $value            => 'null',
            default                    => get_debug_type($value),
        };
    }

    /**
     * @param list<string> $types
     *
     * @throws ComposerFileException When a present field has none of the accepted JSON types
     */
    private static function assertUnionField(
        stdClass $document,
        string   $field,
        array    $types,
        string   $context,
    ): void
    {
        if (!property_exists($document, $field)) {
            return;
        }

        $actualType = self::jsonType($document->{$field});

        if (!in_array($actualType, $types, true)) {
            throw new ComposerFileException(sprintf(
                '%s field "%s" must be %s; %s given.',
                $context,
                $field,
                implode(' or ', $types),
                $actualType,
            ));
        }
    }

    /**
     * @throws ComposerFileException When a present field is not a list of strings
     */
    private static function assertStringListField(stdClass $document, string $field, string $context): void
    {
        if (!property_exists($document, $field)) {
            return;
        }

        self::assertStringList($document->{$field}, sprintf('%s field "%s"', $context, $field));
    }

    /**
     * @throws ComposerFileException When the value is not a list of strings
     */
    private static function assertStringList(mixed $value, string $context): void
    {
        self::assertValueType($value, 'array', $context);

        foreach ($value as $index => $item) {
            self::assertValueType($item, 'string', sprintf('%s[%d]', $context, $index));
        }
    }

    /**
     * @throws ComposerFileException When a present field is neither a string nor a list of strings
     */
    private static function assertStringOrStringListField(stdClass $document, string $field, string $context): void
    {
        if (!property_exists($document, $field) || is_string($document->{$field})) {
            return;
        }

        self::assertStringList($document->{$field}, sprintf('%s field "%s"', $context, $field));
    }

    /**
     * @throws ComposerFileException When a present object field contains a value of the wrong JSON type
     */
    private static function assertObjectValueTypeField(
        stdClass $document,
        string   $field,
        string   $expectedType,
        string   $context,
    ): void
    {
        if (!property_exists($document, $field)) {
            return;
        }

        if ([] === $document->{$field}) {
            return;
        }

        self::assertValueType($document->{$field}, 'object', sprintf('%s field "%s"', $context, $field));

        foreach (get_object_vars($document->{$field}) as $name => $value) {
            self::assertValueType(
                $value,
                $expectedType,
                sprintf('%s field "%s.%s"', $context, $field, $name),
            );
        }
    }

    /**
     * @throws ComposerFileException When a declared package name violates the Composer package-name grammar
     */
    private static function assertPackageName(stdClass $document, string $context): void
    {
        if (
            property_exists($document, 'name')
            && 1 !== preg_match(self::PACKAGE_NAME_PATTERN, $document->name)
        ) {
            throw new ComposerFileException(sprintf(
                '%s field "name" must be a lowercase Composer package name.',
                $context,
            ));
        }
    }

    /**
     * @throws ComposerFileException When a declared package type violates the Composer type grammar
     */
    private static function assertPackageType(stdClass $document, string $context): void
    {
        if (
            property_exists($document, 'type')
            && 1 !== preg_match(self::PACKAGE_TYPE_PATTERN, $document->type)
        ) {
            throw new ComposerFileException(sprintf('%s field "type" has an invalid Composer package type.', $context));
        }
    }

    /**
     * @throws ComposerFileException When minimum-stability is not an official stability value
     */
    private static function assertMinimumStability(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'minimum-stability')) {
            return;
        }

        if (!in_array($document->{'minimum-stability'}, [
            'dev',
            'alpha',
            'beta',
            'rc',
            'RC',
            'stable',
        ], true)) {
            throw new ComposerFileException(sprintf(
                '%s field "minimum-stability" has an invalid stability value.',
                $context,
            ));
        }
    }

    /**
     * @throws ComposerFileException When authors do not match the official author object structure
     */
    private static function assertAuthors(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'authors')) {
            return;
        }

        foreach ($document->authors as $index => $author) {
            $authorContext = sprintf('%s field "authors"[%d]', $context, $index);
            self::assertValueType($author, 'object', $authorContext);

            if (!property_exists($author, 'name')) {
                throw new ComposerFileException(sprintf('%s must declare field "name".', $authorContext));
            }

            foreach (array_keys(get_object_vars($author)) as $field) {
                if (!in_array($field, self::AUTHOR_FIELDS, true)) {
                    throw new ComposerFileException(
                        sprintf('%s contains unsupported field "%s".', $authorContext, $field),
                    );
                }
            }

            self::assertFieldsHaveType($author, self::AUTHOR_FIELDS, 'string', $authorContext);
        }
    }

    /**
     * @throws ComposerFileException When funding entries are not objects with string type and URL fields
     */
    private static function assertFunding(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'funding')) {
            return;
        }

        foreach ($document->funding as $index => $funding) {
            $fundingContext = sprintf('%s field "funding"[%d]', $context, $index);
            self::assertValueType($funding, 'object', $fundingContext);
            self::assertFieldsHaveType($funding, [
                'type',
                'url',
            ], 'string', $fundingContext);
        }
    }

    /**
     * @throws ComposerFileException When a known support field is not a string
     */
    private static function assertSupport(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'support')) {
            return;
        }

        self::assertFieldsHaveType(
            $document->support,
            self::SUPPORT_STRING_FIELDS,
            'string',
            sprintf('%s field "support"', $context),
        );
    }

    /**
     * @throws ComposerFileException When source metadata is incomplete or structurally invalid
     */
    private static function assertSource(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'source')) {
            return;
        }

        self::assertRequiredStringFields(
            $document->source,
            [
                'type',
                'url',
                'reference',
            ],
            sprintf('%s field "source"', $context),
        );

        if (property_exists($document->source, 'mirrors')) {
            self::assertValueType(
                $document->source->mirrors,
                'array',
                sprintf('%s field "source.mirrors"', $context),
            );
        }
    }

    /**
     * @param list<string> $fields
     *
     * @throws ComposerFileException When required object fields are missing or are not strings
     */
    private static function assertRequiredStringFields(stdClass $object, array $fields, string $context): void
    {
        foreach ($fields as $field) {
            if (!property_exists($object, $field)) {
                throw new ComposerFileException(sprintf('%s must declare field "%s".', $context, $field));
            }
        }

        self::assertFieldsHaveType($object, $fields, 'string', $context);
    }

    /**
     * @throws ComposerFileException When distribution metadata is incomplete or structurally invalid
     */
    private static function assertDist(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'dist')) {
            return;
        }

        self::assertRequiredStringFields(
            $document->dist,
            [
                'type',
                'url',
            ],
            sprintf('%s field "dist"', $context),
        );
        self::assertFieldsHaveType(
            $document->dist,
            [
                'reference',
                'shasum',
            ],
            'string',
            sprintf('%s field "dist"', $context),
        );

        if (property_exists($document->dist, 'mirrors')) {
            self::assertValueType(
                $document->dist->mirrors,
                'array',
                sprintf('%s field "dist.mirrors"', $context),
            );
        }
    }

    /**
     * @throws ComposerFileException When autoload rules do not match official Composer structures
     */
    private static function assertAutoload(stdClass $document, string $field, string $context): void
    {
        if (!property_exists($document, $field)) {
            return;
        }

        $autoload = $document->{$field};
        $autoloadContext = sprintf('%s field "%s"', $context, $field);

        foreach (self::AUTOLOAD_NAMESPACE_FIELDS as $namespaceField) {
            if (!property_exists($autoload, $namespaceField)) {
                continue;
            }

            self::assertValueType(
                $autoload->{$namespaceField},
                'object',
                sprintf('%s.%s', $autoloadContext, $namespaceField),
            );

            foreach (get_object_vars($autoload->{$namespaceField}) as $prefix => $paths) {
                $mappingContext = sprintf('%s.%s field "%s"', $autoloadContext, $namespaceField, $prefix);

                if (is_string($paths)) {
                    continue;
                }

                self::assertStringList($paths, $mappingContext);
            }
        }

        foreach (self::AUTOLOAD_PATH_FIELDS as $pathField) {
            if (property_exists($autoload, $pathField)) {
                self::assertStringList($autoload->{$pathField}, sprintf('%s.%s', $autoloadContext, $pathField));
            }
        }
    }

    /**
     * @throws ComposerFileException When archive fields do not match official Composer structures
     */
    private static function assertArchive(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'archive')) {
            return;
        }

        $archiveContext = sprintf('%s field "archive"', $context);
        self::assertFieldsHaveType($document->archive, [
            'name',
        ], 'string', $archiveContext);

        if (property_exists($document->archive, 'exclude')) {
            self::assertStringList($document->archive->exclude, sprintf('%s.exclude', $archiveContext));
        }
    }

    /**
     * @throws ComposerFileException When script declarations are neither strings nor lists of strings
     */
    private static function assertScripts(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'scripts')) {
            return;
        }

        foreach (get_object_vars($document->scripts) as $name => $script) {
            if (is_string($script)) {
                continue;
            }

            self::assertStringList($script, sprintf('%s field "scripts.%s"', $context, $name));
        }
    }

    /**
     * @throws ComposerFileException When script aliases are not lists of strings
     */
    private static function assertScriptsAliases(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'scripts-aliases')) {
            return;
        }

        foreach (get_object_vars($document->{'scripts-aliases'}) as $name => $aliases) {
            self::assertStringList($aliases, sprintf('%s field "scripts-aliases.%s"', $context, $name));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeObject(stdClass $object): array
    {
        return array_map(function ($value) {
            return self::normalizeValue($value);
        }, get_object_vars($object));
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            return self::normalizeObject($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        return array_map(function ($item) {
            return self::normalizeValue($item);
        }, $value);
    }

    /**
     * Reads a `composer.lock` file and returns its validated field view.
     *
     * @throws ComposerFileException When the path does not name composer.lock or the file is unreadable or invalid
     */
    public static function readComposerLock(string $path): ComposerLockInterface
    {
        self::assertFilename($path, 'composer.lock');
        $document = self::decodeObject($path);
        self::validateLockDocument($document, sprintf('composer.lock at %s', $path));

        return new ComposerLock(self::normalizeObject($document));
    }

    /**
     * @throws ComposerFileException When an official composer.lock field has an invalid structure
     */
    private static function validateLockDocument(stdClass $document, string $context): void
    {
        foreach (self::LOCK_REQUIRED_FIELDS as $field) {
            if (!property_exists($document, $field)) {
                throw new ComposerFileException(sprintf('%s must declare field "%s".', $context, $field));
            }
        }

        self::assertFieldsHaveType($document, self::LOCK_STRING_FIELDS, 'string', $context);
        self::assertFieldsHaveType($document, self::LOCK_BOOLEAN_FIELDS, 'boolean', $context);
        self::assertFieldsHaveType($document, self::LOCK_ARRAY_FIELDS, 'array', $context);
        self::assertLockObjectFields($document, $context);

        self::assertStringListField($document, '_readme', $context);
        self::assertPackages($document->packages, sprintf('%s field "packages"', $context));
        self::assertPackages($document->{'packages-dev'}, sprintf('%s field "packages-dev"', $context));
        self::assertAliases($document, $context);
        self::assertObjectValueTypeField($document, 'stability-flags', 'integer', $context);

        foreach (self::LOCK_PLATFORM_FIELDS as $field) {
            self::assertObjectValueTypeField($document, $field, 'string', $context);
        }
    }

    /**
     * Composer serializes some empty lock-file object maps as JSON arrays.
     *
     * @throws ComposerFileException When a present field is neither an object nor an empty array
     */
    private static function assertLockObjectFields(stdClass $document, string $context): void
    {
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
                self::jsonType($value),
            ));
        }
    }

    /**
     * @param list<mixed> $packages
     *
     * @throws ComposerFileException When a lock-file package entry is not an object with valid package metadata
     */
    private static function assertPackages(array $packages, string $context): void
    {
        foreach ($packages as $index => $package) {
            $packageContext = sprintf('%s[%d]', $context, $index);
            self::assertValueType($package, 'object', $packageContext);
            self::assertRequiredStringFields($package, [
                'name',
                'version',
            ], $packageContext);
            self::validateComposerDocument($package, $packageContext);
        }
    }

    /**
     * @throws ComposerFileException When lock-file aliases do not match the official structure
     */
    private static function assertAliases(stdClass $document, string $context): void
    {
        if (!property_exists($document, 'aliases')) {
            return;
        }

        foreach ($document->aliases as $index => $alias) {
            $aliasContext = sprintf('%s field "aliases"[%d]', $context, $index);
            self::assertValueType($alias, 'object', $aliasContext);
            self::assertRequiredStringFields($alias, self::ALIAS_REQUIRED_FIELDS, $aliasContext);
        }
    }
}
