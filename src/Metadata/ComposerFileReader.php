<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use SuperKernel\Composer\Constants\ComposerFile;
use SuperKernel\Composer\Contract\ComposerJsonInterface;
use SuperKernel\Composer\Contract\ComposerLockInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Metadata\Reader\ComposerJsonReader;
use SuperKernel\Composer\Metadata\Reader\ComposerLockReader;

use function basename;
use function sprintf;

/**
 * Reads and structurally validates composer.json and `composer.lock` files without network or Composer runtime access.
 */
final class ComposerFileReader
{
    /**
     * Reports whether a supported Composer file is readable, valid JSON, and structurally valid.
     */
    public static function validate(string $path): bool
    {
        try {
            match (ComposerFile::tryFrom(basename($path))) {
                ComposerFile::Json => ComposerJsonReader::read($path),
                ComposerFile::Lock => ComposerLockReader::read($path),
                null               => throw new ComposerFileException(sprintf(
                    'Expected a composer.json or composer.lock path, got %s.',
                    $path,
                )),
            };
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
        return match (ComposerFile::tryFrom(basename($path))) {
            ComposerFile::Json => ComposerJsonReader::read($path),
            ComposerFile::Lock => ComposerLockReader::read($path),
            null               => throw new ComposerFileException(sprintf(
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
        return ComposerJsonReader::read($path);
    }

    /**
     * Reads a `composer.lock` file and returns its validated field view.
     *
     * @throws ComposerFileException When the path does not name composer.lock or the file is unreadable or invalid
     */
    public static function readComposerLock(string $path): ComposerLockInterface
    {
        return ComposerLockReader::read($path);
    }
}
