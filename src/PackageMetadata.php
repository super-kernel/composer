<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\PackageMetadataInterface;

final readonly class PackageMetadata implements PackageMetadataInterface
{
	public function __construct(
		private string  $name,
		private array   $attributes,
		private array   $classMap,
		private ?string $reference = null,
	)
	{
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getReference(): ?string
	{
		return $this->reference;
	}
}