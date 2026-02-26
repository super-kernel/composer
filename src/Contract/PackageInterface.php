<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface PackageInterface
{
	public function isRootPackage(): bool;

	public function getName(): string;

	public function getType(): string;

	public function getReference(): ?string;

	public function getAutoload(): array;

	public function getDevAutoload();
}
