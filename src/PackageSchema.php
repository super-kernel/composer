<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\PackageSchemaInterface;
use SuperKernel\Composer\Enum\PackageTypeEnum;

final readonly class PackageSchema implements PackageSchemaInterface
{
	public function __construct(public array $data, public bool $devRequirements)
	{
	}

	public function isDevRequirement(): bool
	{
		return $this->devRequirements;
	}

	public function getName(): string
	{
		return $this->data['name'];
	}

	public function getVersion(): string
	{
		return $this->data['version'];
	}

	public function getType(): PackageTypeEnum
	{
		return $this->data['type'];
	}

	public function getAutoload(): array
	{
		return $this->data['autoload'];
	}
}