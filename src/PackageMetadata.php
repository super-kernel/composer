<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\PackageMetadataInterface;
use SuperKernel\Composer\Contract\PackageSchemaMetadataInterface;

final readonly class PackageMetadata implements PackageMetadataInterface
{
	private array $packages;

	public function __construct(PackageSchemaMetadataInterface $packageSchemaMetadata)
	{
	}

	public function getName(): string
	{
		// TODO: Implement getName() method.
	}

	public function getType(): string
	{
		// TODO: Implement getType() method.
	}
}