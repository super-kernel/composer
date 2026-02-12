<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\ComposerConfigInterface;
use SuperKernel\Composer\Contract\PackageSchemaInterface;

final readonly class ComposerConfig implements ComposerConfigInterface
{
	private string $vendorDir;

	private array $composerJsonData;

	public function __construct(private string $path, private bool $includeDevRequirements = true)
	{
		$this->composerJsonData = Support::loadComposerFileToArray($this->path . '/composer.json');

		$this->vendorDir = $this->composerJsonData['config']['vendor-dir'] ?? 'vendor';
	}

	public function getPath(): string
	{
		return $this->path;
	}

	public function getRootPackageSchema(): PackageSchemaInterface
	{
		return new PackageSchema($this->composerJsonData, false);
	}

	public function includeDevRequirements(): bool
	{
		return $this->includeDevRequirements;
	}

	public function getVendorDir(): string
	{
		return $this->vendorDir;
	}
}