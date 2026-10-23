<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Exception;

use RuntimeException;

final class ComposerFileException extends RuntimeException implements MetadataExceptionInterface
{
}
