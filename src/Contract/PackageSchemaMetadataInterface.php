<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

interface PackageSchemaMetadataInterface
{
	/**
	 * Retrieve the root package.
	 *
	 * @return PackageSchemaInterface The root package instance.
	 */
	public function getRootPackage(): PackageSchemaInterface;

	/**
	 * Retrieve a locked package by name.
	 *
	 * @param string $packageName The package name (e.g. vendor/package).
	 *
	 * @return PackageSchemaInterface|null The package instance if found, otherwise null.
	 */
	public function getPackage(string $packageName): ?PackageSchemaInterface;

	/**
	 * Determine whether a locked package exists.
	 *
	 * @param string $packageName The package name.
	 *
	 * @return bool True if the package exists, false otherwise.
	 */
	public function hasPackage(string $packageName): bool;

	/**
	 * Retrieve all locked packages.
	 *
	 * @param bool $requireDev Whether to include development packages.
	 *
	 * @return array<string, PackageSchemaInterface> All locked packages indexed by package name.
	 */
	public function getAllPackages(bool $requireDev = true): array;

	/**
	 * Retrieve the raw `composer.lock` data.
	 *
	 * @return array<string, mixed> The decoded lock file data.
	 */
	public function getRawData(): array;
}