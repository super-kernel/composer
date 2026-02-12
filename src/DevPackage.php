<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

final readonly class DevPackage
{
	public function __construct(private array $data)
	{
	}

	public function getName(): string
	{
		return $this->data['name'];
	}

	public function getType(): string
	{
		return $this->data['type'];
	}

	public function getAutoload(): array
	{
		return $this->data['autoload'] ?? [];
	}

	public function getAutoloadDev(): array
	{
		return $this->data['autoload-dev'] ?? [];
	}
}