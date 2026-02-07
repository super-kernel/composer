<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use SuperKernel\Composer\Contract\ComposerConfigInterface;

final readonly class ComposerConfig implements ComposerConfigInterface
{
	public function __construct(public string $path)
	{
	}

	public function getPath(): string
	{
		return $this->path;
	}
}