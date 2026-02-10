<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Schema;

use SuperKernel\Composer\Abstract\AbstractPackageSchema;
use SuperKernel\Composer\Contract\PackageSchemaInterface;

final readonly class RootPackageSchema extends AbstractPackageSchema implements PackageSchemaInterface
{
}