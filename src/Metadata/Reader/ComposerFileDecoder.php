<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Reader;

use JsonException;
use stdClass;
use SuperKernel\Composer\Constants\ComposerFile;
use SuperKernel\Composer\Exception\ComposerFileException;

use function basename;
use function file_get_contents;
use function is_file;
use function is_readable;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Checks Composer file names and decodes their top-level JSON objects.
 *
 * @internal
 */
final class ComposerFileDecoder
{
    /**
     * @throws ComposerFileException When the path does not have the expected basename
     */
    public static function assertFilename(string $path, ComposerFile $expected): void
    {
        if ($expected->value !== basename($path)) {
            throw new ComposerFileException(sprintf('Expected %s path, got %s.', $expected->value, $path));
        }
    }

    /**
     * @throws ComposerFileException When the file is unavailable, unreadable, malformed, or not a JSON object
     */
    public static function decodeObject(string $path): stdClass
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
}
