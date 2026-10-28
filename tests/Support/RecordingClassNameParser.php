<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Support;

use SuperKernel\Tokenizer\ClassNameParser;
use SuperKernel\Tokenizer\Contract\ClassNameParserInterface;

final class RecordingClassNameParser implements ClassNameParserInterface
{
    /** @var list<string> */
    public array $sources = [];

    public function parse(string $source): array|false
    {
        $this->sources[] = $source;

        return new ClassNameParser()->parse($source);
    }
}
