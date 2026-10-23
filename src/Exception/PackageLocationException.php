<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Exception;

use RuntimeException;

final class PackageLocationException extends RuntimeException implements MetadataExceptionInterface
{
}
