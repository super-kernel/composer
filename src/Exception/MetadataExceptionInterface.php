<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Exception;

use Throwable;

/**
 * Marks every domain error produced while reading Composer project metadata.
 */
interface MetadataExceptionInterface extends Throwable
{
}
