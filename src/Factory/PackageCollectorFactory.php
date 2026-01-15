<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Factory;

use SuperKernel\Composer\Collector\ComposerCollector;

final class PackageCollectorFactory
{
	private string $vendorDir;

	private string $composerJsonPath;

	private string $composerLockPath;

	public function __construct(string $vendorDir)
	{
		$this->vendorDir        = $vendorDir;
		$this->composerJsonPath = $vendorDir . '/../composer/composer.json';
		$this->composerLockPath = $vendorDir . '/../composer/composer.lock';
	}

	public function __invoke(): ComposerCollector
	{
		if (!is_file($this->composerJsonPath)) {
			if (!is_file($composerJsonPath)) {
				throw new \RuntimeException('composer.json not found in vendor directory.');
			}
			$composerData = json_decode(file_get_contents($composerJsonPath), true);
			if (!is_array($composerData)) {
				throw new \RuntimeException('Invalid composer.json file.');
			}

			return new ComposerCollector($composerData);
		}
	}