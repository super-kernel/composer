<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Provider;

use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Composer\Contract\DetectorInterface;
use SuperKernel\Composer\Detector\ComponentDetector;
use SuperKernel\Composer\Detector\ComposerScriptDetector;
use SuperKernel\Composer\Detector\NullDetector;
use SuperKernel\Composer\Detector\ScriptDetector;
use SuperKernel\Composer\Detector\WorkingDirectoryDetector;
use SuperKernel\Composer\Exception\ProjectRootNotFoundException;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Contract\BasePathInterface;

#[FactoryProvider(BasePathInterface::class)]
final readonly class BasePathProvider
{
    /**
     * Selects Phar, Composer script, PHP entry, working directory, then the component detector requiring a lock file.
     * Filesystem detectors inspect only the current working directory.
     * The first supporting detector wins; later detectors are not evaluated.
     * The selected detector's directory is trusted.
     *
     * @param iterable|null $detectors Ordered custom detectors, or null for the default sequence
     *
     * @psalm-param iterable<DetectorInterface>|null $detectors
     *
     * @return BasePathInterface A fresh value constructed from the selected detector's directory
     *
     * @throws ProjectRootNotFoundException When no detector supports the environment
     */
    public function __invoke(?iterable $detectors = null): BasePathInterface
    {
        // The default sequence is a generator, so detectors after the selected one are never constructed.
        $detectors ??= (static function (): iterable {
            yield new NullDetector();
            yield new ComposerScriptDetector();
            yield new ScriptDetector();
            yield new WorkingDirectoryDetector();
            yield new ComponentDetector();
        })();

        foreach ($detectors as $detector) {
            if ($detector->support()) {
                return new BasePath($detector->projectDirectory());
            }
        }

        throw new ProjectRootNotFoundException('No detector supports the current project environment.');
    }
}
