<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\PackageInterface;

final readonly class Package implements PackageInterface
{
	public function __construct(private array $data, private bool $rootPackage = false)
	{
	}

	public function isRootPackage(): bool
	{
		return $this->rootPackage;
	}

	public function getName(): string
	{
		return $this->data['name'];
	}

	public function getType(): string
	{
		return $this->data['type'];
	}

	public function getReference(): ?string
	{
		return $this->data['dist']['reference'] ?? null;
	}

	public function getAutoload(): array
	{
		return $this->data['autoload'] ?? [];
	}

	public function getDevAutoload(): array
	{
		return $this->data['autoload-dev'] ?? [];
	}
}