<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Exception;

use RuntimeException;

class AutoloadRuleException extends RuntimeException implements MetadataExceptionInterface
{
}
