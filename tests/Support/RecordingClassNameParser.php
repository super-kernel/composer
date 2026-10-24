<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Support;

use Psr\Http\Message\StreamInterface;
use SuperKernel\Tokenizer\ClassNameParser;
use SuperKernel\Tokenizer\Contract\ClassNameParserInterface;

use function is_string;

final class RecordingClassNameParser implements ClassNameParserInterface
{
    /** @var list<string> */
    public array $files = [];

    public function parse(StreamInterface $stream): array|false
    {
        $uri = $stream->getMetadata('uri');
        $this->files[] = is_string($uri) ? $uri : '';

        return new ClassNameParser()->parse($stream);
    }

    /**
     * @noinspection PhpDeprecationInspection The interface still declares the deprecated method until it is removed.
     */
    public function parseFile(string $file): array|false
    {
        $this->files[] = $file;

        return new ClassNameParser()->parseFile($file);
    }
}
