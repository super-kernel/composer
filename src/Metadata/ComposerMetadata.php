<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use SuperKernel\Attribute\Provider;
use SuperKernel\Composer\Constants\ComposerFile;
use SuperKernel\Composer\Constants\PackageType;
use SuperKernel\Composer\Contract\ComposerMetadataInterface;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Exception\PackageNotFoundException;
use SuperKernel\Composer\Path\FilesystemPath;
use SuperKernel\Contract\BasePathInterface;

use function array_values;
use function ksort;
use function sprintf;
use function str_contains;
use function strtolower;

use const SORT_STRING;

#[Provider(ComposerMetadataInterface::class)]
final readonly class ComposerMetadata implements ComposerMetadataInterface
{
    private ComposerPackageInterface $root;

    /** @var array<string, ComposerPackageInterface> */
    private array $byName;

    /** @var list<ComposerPackageInterface> */
    private array $packages;

    /** @var array<array-key, non-empty-list<ComposerPackageInterface>> */
    private array $byType;

    /**
     * Reads native package data from the supplied project directory.
     * Reading metadata does not resolve or require a vendor installation.
     *
     * @throws ComposerFileException When paths, Composer documents, or package identities are invalid
     */
    public function __construct(BasePathInterface $basePath)
    {
        $directory = (string)$basePath;

        if ('' === $directory || str_contains($directory, "\0")) {
            throw new ComposerFileException('A non-empty Composer project directory without null bytes is required.');
        }

        $root = FilesystemPath::canonicalDirectory($directory);

        if (null === $root) {
            throw new ComposerFileException(
                sprintf('Composer project directory is unavailable: %s.', $directory),
            );
        }

        $json = ComposerFileReader::readComposerJson($root . '/' . ComposerFile::Json->value);
        $lock = ComposerFileReader::readComposerLock($root . '/' . ComposerFile::Lock->value);
        $this->root = new ComposerPackage($json->all(), root: true);
        $byName = [];
        $packages = [];
        $rootName = $this->root->name();

        if (null !== $rootName) {
            $byName[$rootName] = $this->root;
        }

        foreach ([
            $lock->packages(),
            $lock->packagesDev(),
        ] as $scope => $entries) {
            foreach ($entries as $entry) {
                $package = new ComposerPackage($entry, development: 1 === $scope);
                $name = $package->name();

                if (null === $name || isset($byName[$name])) {
                    throw new ComposerFileException(sprintf(
                        'Composer metadata requires unique package names; received %s.',
                        $name ?? 'an unnamed lock package',
                    ));
                }

                $byName[$name] = $package;
                $packages[$name] = $package;
            }
        }

        ksort($packages, SORT_STRING);
        $byType = [
            PackageType::Root->value => [
                $this->root,
            ],
        ];

        foreach ($packages as $package) {
            $type = $package->type();
            $key = $type instanceof PackageType ? $type->value : $type;
            $byType[$key][] = $package;
        }

        ksort($byType, SORT_STRING);
        $this->byName = $byName;
        $this->packages = array_values($packages);
        $this->byType = $byType;
    }

    public function has(string $name): bool
    {
        return isset($this->byName[strtolower($name)]);
    }

    /**
     * @throws PackageNotFoundException When the caller has not established that the requested package exists
     */
    public function get(string $name): ComposerPackageInterface
    {
        return $this->byName[strtolower($name)] ?? throw new PackageNotFoundException(sprintf(
            'Composer package "%s" is not present in the metadata.',
            $name,
        ));
    }

    public function root(): ComposerPackageInterface
    {
        return $this->root;
    }

    public function all(): array
    {
        return $this->packages;
    }

    public function ofType(PackageType|string $type): array
    {
        $key = $type instanceof PackageType ? $type->value : $type;

        return $this->byType[$key] ?? [];
    }

    public function groupedByType(): array
    {
        return $this->byType;
    }

}
