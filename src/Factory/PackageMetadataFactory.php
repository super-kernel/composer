<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use AppendIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SuperKernel\Annotation\Factory;
use SuperKernel\Annotation\Provider;
use SuperKernel\Composer\Ast\TokenClassParser;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\PackageMetadataInterface;
use SuperKernel\Composer\PackageMetadata;
use SuperKernel\PathResolver\Interface\PathResolverInterface;
use function array_filter;
use function array_merge_recursive;
use function array_unique;
use function array_walk_recursive;
use function file_get_contents;
use function is_array;
use function is_dir;
use function realpath;
use function str_replace;
use function trim;
use const DIRECTORY_SEPARATOR;

#[
	Provider(PackageMetadataInterface::class),
	Factory,
]
final readonly class PackageMetadataFactory
{
	private TokenClassParser $parser;

	private string $vendorDir;

	public function __construct(private PathResolverInterface $pathResolver)
	{
		$this->parser    = new TokenClassParser();
		$this->vendorDir = $this->pathResolver->resolve('vendor');
	}

	public function create(PackageInterface $package): PackageMetadataInterface
	{
		$packageName = $package->getName();
		$packagePath = $this->getPackagePath($package);
		$targetDirs  = $this->getScanDirectories($package);

		$classmap = [];

		$combinedIterator = new AppendIterator();
		foreach ($targetDirs as $relDir) {
			$absPath = realpath($packagePath . DIRECTORY_SEPARATOR . $relDir);

			if ($absPath && is_dir($absPath)) {
				$directory = new RecursiveDirectoryIterator($absPath, FilesystemIterator::SKIP_DOTS);
				$combinedIterator->append(new RecursiveIteratorIterator($directory));
			}
		}

		/** @var SplFileInfo $file */
		foreach ($combinedIterator as $file) {
			if ('php' === $file->getExtension()) {
				$this->processFile($file, $classmap);
			}
		}

		return new PackageMetadata($packageName, $classmap);
	}

	private function getPackagePath(PackageInterface $package): string
	{
		return $package->isRootPackage()
			? $this->pathResolver->resolve()
			: $this->vendorDir . DIRECTORY_SEPARATOR . $package->getName();
	}

	private function getScanDirectories(PackageInterface $package): array
	{
		$autoloads = $package->getAutoload();

		if ($package->isRootPackage()) {
			$autoloads = array_merge_recursive($autoloads, $package->getDevAutoload());
		}

		$dirs = [];
		foreach (['psr-4', 'psr-0'] as $type) {
			if (isset($autoloads[$type]) && is_array($autoloads[$type])) {
				foreach ($autoloads[$type] as $paths) {
					if (is_array($paths)) {
						array_walk_recursive($paths, function ($p) use (&$dirs) {
							$dirs[] = $this->normalizePath((string)$p);
						});
					} else {
						$dirs[] = $this->normalizePath((string)$paths);
					}
				}
			}
		}

		return array_unique(array_filter($dirs));
	}

	private function normalizePath(string $path): string
	{
		return trim($path, "./\\ ");
	}

	private function processFile(SplFileInfo $file, array &$classmap): void
	{
		$realPath = $file->getRealPath();
		$content  = file_get_contents($realPath);

		$classname = $this->parser->getFullyQualifiedClassName($content);

		if ($classname) {
			$classmap[$classname] = str_replace($this->pathResolver . DIRECTORY_SEPARATOR, '', $realPath);
		}
	}
}