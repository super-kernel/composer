<?php
declare(strict_types=1);

namespace SuperKernel\Composer;

use RuntimeException;
use SuperKernel\Composer\Contract\PackageSchemaInterface;
use function preg_replace;
use function property_exists;
use function strtolower;

final readonly class PackageSchema implements PackageSchemaInterface
{
	public function __construct(private array $rows)
	{
	}

	public function getName(): string
	{
		return $this->rows['name'];
	}

	public function getVersion(): ?string
	{
		return $this->rows['version'] ?? null;
	}

	public function getVersionNormalized(): ?string
	{
		return $this->rows['version-normalized'] ?? null;
	}

	public function getType(): ?string
	{
		return $this->rows['type'] ?? null;
	}

	public function getDescription(): ?string
	{
		return $this->rows['description'] ?? null;
	}

	public function getLicense(): string|array|null
	{
		return $this->rows['license'] ?? null;
	}

	public function getHomepage(): ?string
	{
		return $this->rows['homepage'] ?? null;
	}

	public function getKeywords(): ?array
	{
		return $this->rows['keywords'] ?? null;
	}

	public function getReadme(): ?string
	{
		return $this->rows['readme'] ?? null;
	}

	public function getTime(): ?string
	{
		return $this->rows['time'] ?? null;
	}

	public function getAuthors(): ?array
	{
		return $this->rows['authors'] ?? null;
	}

	public function getSupport(): ?array
	{
		return $this->rows['support'] ?? null;
	}

	public function getFunding(): ?array
	{
		return $this->rows['funding'] ?? null;
	}

	public function getRequire(): ?array
	{
		return $this->rows['require'] ?? null;
	}

	public function getRequireDev(): ?array
	{
		return $this->rows['require-dev'] ?? null;
	}

	public function getConflict(): ?array
	{
		return $this->rows['conflict'] ?? null;
	}

	public function getReplace(): ?array
	{
		return $this->rows['replace'] ?? null;
	}

	public function getProvide(): ?array
	{
		return $this->rows['provide'] ?? null;
	}

	public function getSuggest(): ?array
	{
		return $this->rows['suggest'] ?? null;
	}

	public function getExtra(): ?array
	{
		return $this->rows['extra'] ?? null;
	}

	public function getBin(): ?array
	{
		return $this->rows['bin'] ?? null;
	}

	public function getArchive(): ?array
	{
		return $this->rows['archive'] ?? null;
	}

	public function getSource(): ?array
	{
		return $this->rows['source'] ?? null;
	}

	public function getDist(): ?array
	{
		return $this->rows['dist'] ?? null;
	}

	public function getAutoload(): ?array
	{
		return $this->rows['autoload'] ?? null;
	}

	public function getAutoloadDev(): ?array
	{
		return $this->rows['autoload-dev'] ?? null;
	}

	public function getAbandoned(): bool|string|null
	{
		return $this->rows['abandoned'] ?? null;
	}

	public function __get(string $name): mixed
	{
		$name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));

		if (property_exists($this, $name)) {
			return $this->{$name};
		}

		throw new RuntimeException(sprintf('Property "%s" does not exist.', $name));
	}
}