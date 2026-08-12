<?php

declare(strict_types=1);

namespace FullSystem\Install\Support;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Runs the real thing.
 *
 * A TTY is used when there is one, so composer, npm and the shadcn CLI keep
 * the output they were designed for. cpx already hands us one when the
 * terminal is interactive.
 */
final class SystemProcess implements ProcessRunner
{
    /** Installs can take a while; no timeout is better than a truncated one. */
    private const ?int TIMEOUT = null;

    public function run(array $command, string $cwd): int
    {
        try {
            $process = new Process($command, cwd: $cwd, timeout: self::TIMEOUT);

            if (Process::isTtySupported() && stream_isatty(STDOUT)) {
                $process->setTty(true);

                return $process->run();
            }

            return $process->run(static function (string $type, string $buffer): void {
                fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
            });
        } catch (ExceptionInterface) {
            return 127;
        }
    }
}
