<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Support;

use SuperKernel\Tokenizer\ClassNameParser;
use SuperKernel\Tokenizer\Contract\ClassNameParserInterface;

final class RecordingClassNameParser implements ClassNameParserInterface
{
    /** @var list<string> */
    public array $files = [];

    public function parseFile(string $file): array|false
    {
        $this->files[] = $file;

        return new ClassNameParser()->parseFile($file);
    }
}
