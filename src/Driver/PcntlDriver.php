<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Driver;

use Phar;
use RuntimeException;
use SuperKernel\Composer\Contract\DriverInterface;
use Throwable;
use function extension_loaded;
use function pcntl_fork;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function strlen;
use const PHP_SAPI;

final readonly class PcntlDriver implements DriverInterface
{
	public function supports(): bool
	{
		if ('cli' !== PHP_SAPI) {
			return false;
		}
		if (!extension_loaded('pcntl')) {
			return false;
		}
		if (strlen(Phar::running(false)) !== 0) {
			return false;
		}

		return true;
	}

	public function execute(callable $task): void
	{
		$pid = pcntl_fork();

		if ($pid === -1) {
			throw new RuntimeException("Process fork failed.");
		}

		if ($pid > 0) {
			pcntl_waitpid($pid, $status);

			$exitCode = pcntl_wexitstatus($status);
			if ($exitCode !== 0) {
				throw new RuntimeException("Scanner process exited with error code: $exitCode");
			}
		} else {
			try {
				$task();
				exit(0);
			}
			catch (Throwable) {
				exit(1);
			}
		}
	}
}