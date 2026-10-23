<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Metadata;

use stdClass;
use SuperKernel\Attribute\Provider;
use SuperKernel\Composer\Contract\ComposerAutoloadInterface;
use SuperKernel\Composer\Contract\ComposerPackageInterface;
use SuperKernel\Composer\Contract\PackageInterface;
use SuperKernel\Composer\Contract\PackageLocatorInterface;
use SuperKernel\Composer\Contract\PackageParserInterface;
use SuperKernel\Composer\Enum\PackageType;
use SuperKernel\Composer\Exception\AutoloadRuleException;
use SuperKernel\Composer\Exception\LockFileException;
use SuperKernel\Composer\Exception\ManifestException;
use SuperKernel\Composer\Exception\PackageLocationException;
use SuperKernel\Composer\Model\Package;
use SuperKernel\Composer\Model\ScanRuleSet;
use SuperKernel\Composer\Path\FilesystemPath;
use SuperKernel\Contract\BasePathInterface;
use SuperKernel\Contract\VendorDirInterface;

use function sprintf;

/**
 * Adapts native Composer package fields to located runtime packages without reading another metadata source.
 *
 * @internal
 */
#[Provider(PackageParserInterface::class)]
final readonly class PackageParser implements PackageParserInterface
{
    public function __construct(
        private BasePathInterface       $basePath,
        private VendorDirInterface      $vendorDir,
        private PackageLocatorInterface $locator,
    )
    {
    }

    public function locate(ComposerPackageInterface $metadata): ?string
    {
        if (PackageType::Root === $metadata->type()) {
            return (string) $this->basePath;
        }

        $type = self::composerType($metadata);

        if ('metapackage' === $type) {
            return null;
        }

        $name = self::name($metadata);
        $vendorDirectory = (string) $this->vendorDir;

        $path = $this->locator->locate($name, $type, $vendorDirectory);
        $path = null === $path ? null : FilesystemPath::absolute($vendorDirectory, $path);

        return FilesystemPath::canonicalDirectory($path) ?? throw new PackageLocationException(sprintf(
            'Package "%s" has no existing directory (vendor directory: "%s"; candidate: %s).',
            $name,
            $vendorDirectory,
            $path ?? 'none',
        ));
    }

    public function parse(ComposerPackageInterface $metadata, ?string $locatedPath): PackageInterface
    {
        $name = self::name($metadata);
        $root = PackageType::Root === $metadata->type();
        $type = self::composerType($metadata);

        $requirements = (object) [
            'require'     => (object) $metadata->require(),
            'require-dev' => (object) $metadata->requireDev(),
        ];
        $requirements = $root
            ? RequirementProcessor::root($requirements, $name)
            : RequirementProcessor::locked($requirements, $name, $name);

        try {
            AutoloadRuleParser::assertSupportedPackageFields((object) $metadata->metadata());
            $production = AutoloadRuleParser::parse(self::autoload($metadata->autoload()), $locatedPath);
            $development = $root
                ? ScanRuleSet::merge(
                    $production,
                    AutoloadRuleParser::parse(self::autoload($metadata->autoloadDev()), $locatedPath),
                )
                : null;
        } catch (AutoloadRuleException $exception) {
            if ($root) {
                throw $exception;
            }

            throw new LockFileException(
                sprintf('Invalid autoload metadata for locked package "%s": %s', $name, $exception->getMessage()),
                previous: $exception,
            );
        }

        return new Package(
            $name,
            $metadata->version(),
            $root ? null : $metadata->reference(),
            $metadata->type(),
            $metadata->isDev(),
            $root ? (string) $this->basePath : $locatedPath,
            $production,
            $requirements->dependencies(),
            $requirements->environmentRequirements(),
            $development,
            $root ? $type : null,
        );
    }

    public function autoloadFiles(ComposerPackageInterface $metadata, ?string $locatedPath): array
    {
        $root = PackageType::Root === $metadata->type();

        return [
            ...AutoloadRuleParser::files(self::autoload($metadata->autoload()), $locatedPath),
            ...($root ? AutoloadRuleParser::files(self::autoload($metadata->autoloadDev()), $locatedPath) : []),
        ];
    }

    private static function autoload(ComposerAutoloadInterface $autoload): stdClass
    {
        $rules = $autoload->classRules();

        return (object) [
            'psr-0'                 => (object) $rules['psr-0'],
            'psr-4'                 => (object) $rules['psr-4'],
            'classmap'              => $rules['classmap'],
            'exclude-from-classmap' => $rules['exclude-from-classmap'],
            'files'                 => $autoload->files(),
        ];
    }

    private static function composerType(ComposerPackageInterface $metadata): string
    {
        $type = $metadata->type();

        return PackageType::Root === $type
            ? ($metadata->metadata()['type'] ?? 'library')
            : ($type instanceof PackageType ? $type->value : $type);
    }

    /** @throws ManifestException When the runtime root does not declare a package name */
    private static function name(ComposerPackageInterface $metadata): string
    {
        return $metadata->name() ?? throw new ManifestException('Root composer.json must declare a string name.');
    }
}
