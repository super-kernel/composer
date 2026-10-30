<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use SuperKernel\Composer\Constants\PackageType;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Exception\ManifestException;

/**
 * Derives the runtime package name and declared Composer type from a native package record.
 *
 * @internal
 */
final class RuntimePackageFields
{
    /**
     * @throws ManifestException When the runtime root does not declare a package name
     */
    public static function name(ComposerPackageInterface $metadata): string
    {
        return $metadata->name() ?? throw new ManifestException('Root composer.json must declare a string name.');
    }

    /**
     * @return string The declared type, using the manifest type or `library` for the synthetic Root type
     */
    public static function composerType(ComposerPackageInterface $metadata): string
    {
        $type = $metadata->type();

        return PackageType::Root === $type
            ? ($metadata->metadata()['type'] ?? PackageType::Library->value)
            : ($type instanceof PackageType ? $type->value : $type);
    }
}
