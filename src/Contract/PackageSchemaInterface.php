<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

use SuperKernel\Composer\Enum\PackageTypeEnum;

interface PackageSchemaInterface
{
	public function isDevRequirement(): bool;

	public function getName(): string;

	public function getVersion(): ?string;

	public function getType(): PackageTypeEnum;

	public function getAutoload(): array;
}