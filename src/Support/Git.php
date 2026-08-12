<?php

declare(strict_types=1);

namespace FullSystem\Install\Support;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * The few things the installer needs to ask git about the project.
 *
 * Every command is an argument list, never a string: nothing here goes through
 * a shell.
 */
class Git
{
    private const int TIMEOUT = 15;

    /**
     * How many commits the current branch has, or null when the question does
     * not apply — not a repository, or no commits yet.
     */
    public function commitCount(string $cwd): ?int
    {
        $output = $this->capture(['git', 'rev-list', '--count', 'HEAD'], $cwd);

        return $output !== null && ctype_digit($output) ? (int) $output : null;
    }

    /**
     * @param  list<string>  $command
     */
    private function capture(array $command, string $cwd): ?string
    {
        try {
            $process = new Process($command, cwd: $cwd, timeout: self::TIMEOUT);
            $process->run();

            return $process->isSuccessful() ? trim($process->getOutput()) : null;
        } catch (ExceptionInterface) {
            return null;
        }
    }
}
