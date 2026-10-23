<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Exception;

use RuntimeException;

final class LockFileException extends RuntimeException implements MetadataExceptionInterface
{
}
