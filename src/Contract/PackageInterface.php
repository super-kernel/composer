<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface PackageInterface
{
	public function getName(): string;

	public function getReference(): string;

	public function getClassmap(bool $includeDev): array;

	public function getAttributes(): array;
}