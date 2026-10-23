<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Exception;

use RuntimeException;

final class PackageCacheException extends RuntimeException implements MetadataExceptionInterface
{
}
