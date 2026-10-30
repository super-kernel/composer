<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata\Reader;

use SuperKernel\Composer\Constants\ComposerFile;
use SuperKernel\Composer\Contract\ComposerJsonInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Metadata\ComposerJson;
use SuperKernel\Composer\Metadata\Validation\ComposerDocumentValidator;

use function sprintf;

/**
 * Reads a composer.json file and returns its validated field view.
 *
 * @internal
 */
final class ComposerJsonReader
{
    /**
     * @throws ComposerFileException When the path does not name composer.json or the file is unreadable or invalid
     */
    public static function read(string $path): ComposerJsonInterface
    {
        ComposerFileDecoder::assertFilename($path, ComposerFile::Json);
        $document = ComposerFileDecoder::decodeObject($path);
        ComposerDocumentValidator::validate($document, sprintf('composer.json at %s', $path));

        return new ComposerJson(JsonDocumentNormalizer::normalize($document));
    }
}
