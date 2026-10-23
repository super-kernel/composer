<?php

declare(strict_types=1);

namespace SuperKernel\Composer\Tests\Unit;

use Stringable;
use SuperKernel\Composer\Path\BasePath;
use SuperKernel\Composer\Tests\Support\TestCase;
use SuperKernel\Contract\BasePathInterface;
use Throwable;

use function symlink;

final class BasePathTest extends TestCase
{
    /**
     * These assertions protect the public interface contract even when static types already imply it.
     *
     * @throws Throwable When fixture setup or the operation under test throws
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function testStringableValuePreservesTheProvidedDirectory(): void
    {
        $this->withTemporaryDirectory(function (
            string $root,
        ): void {
            $this->makeDirectory($root . '/application');
            self::assertTrue(symlink($root . '/application', $root . '/linked'));
            $directory = $root . '/linked/.';
            $value = new BasePath($directory);

            self::assertInstanceOf(BasePathInterface::class, $value);
            self::assertInstanceOf(Stringable::class, $value);
            self::assertSame($directory, (string)$value);
        });
    }
}
