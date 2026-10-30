<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Manager;

use SuperKernel\Composer\Contract\PackageInterface;

/**
 * Orders package file autoloads so that every dependency's files precede its dependents' files.
 *
 * @internal
 */
final class AutoloadFileOrder
{
    /**
     * Appends the files of the named package after those of its known dependencies, visiting each package once.
     *
     * Unknown package names are ignored, and dependency cycles end at the first package visited again.
     *
     * @param array<string, PackageInterface>       $packages      Packages keyed by name
     * @param array<string, list<non-empty-string>> $autoloadFiles Ordered files keyed by package name
     * @param array<string, true>                   $seen          Visited package names, updated in place
     * @param array<non-empty-string, true>         $files         Ordered unique files, updated in place
     */
    public static function append(
        string $name,
        array  $packages,
        array  $autoloadFiles,
        array &$seen,
        array &$files,
    ): void
    {
        if (isset($seen[$name]) || !isset($packages[$name])) {
            return;
        }

        $seen[$name] = true;

        foreach ($packages[$name]->dependencies() as $dependency) {
            self::append($dependency->name(), $packages, $autoloadFiles, $seen, $files);
        }

        foreach ($autoloadFiles[$name] as $file) {
            $files[$file] = true;
        }
    }
}
