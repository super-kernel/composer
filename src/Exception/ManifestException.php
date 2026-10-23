<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Exception;

use RuntimeException;

final class ManifestException extends RuntimeException implements MetadataExceptionInterface
{
}
