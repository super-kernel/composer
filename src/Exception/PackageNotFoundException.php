<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Exception;

use OutOfBoundsException;

final class PackageNotFoundException extends OutOfBoundsException implements MetadataExceptionInterface
{
}
