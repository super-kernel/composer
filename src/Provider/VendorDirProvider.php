<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Provider;

use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Composer\Contract\ComposerMetadataInterface;
use SuperKernel\Composer\Exception\ComposerFileException;
use SuperKernel\Composer\Path\FilesystemPath;
use SuperKernel\Composer\Path\VendorDir;
use SuperKernel\Contract\BasePathInterface;
use SuperKernel\Contract\VendorDirInterface;

use function array_key_exists;
use function is_string;

#[FactoryProvider(VendorDirInterface::class)]
final readonly class VendorDirProvider
{
    /**
     * Resolves the root package's config.vendor-dir against the supplied base path, defaulting to vendor.
     * Uses the injected metadata snapshot without rereading Composer files.
     *
     * @return VendorDirInterface A fresh normalized directory value; the target does not need to exist
     *
     * @throws ComposerFileException When config.vendor-dir is invalid
     */
    public function __invoke(ComposerMetadataInterface $metadata, BasePathInterface $basePath): VendorDirInterface
    {
        $config = $metadata->root()->config();
        $configured = array_key_exists('vendor-dir', $config) ? $config['vendor-dir'] : 'vendor';
        $directory = is_string($configured) ? FilesystemPath::absolute((string)$basePath, $configured) : null;

        if (null === $directory) {
            throw new ComposerFileException('Root config.vendor-dir must be a non-empty, resolvable path.');
        }

        return new VendorDir($directory);
    }
}
