<?php

declare(strict_types=1);

namespace FullSystem\Install\Support;

/**
 * Runs commands without letting them write to the terminal, keeping the
 * output in case it turns out to be needed.
 *
 * Composer and npm print hundreds of lines about packages nobody asked about.
 * On a run that worked, none of it matters; on one that broke, all of it
 * does — so it is kept rather than discarded, and printed only then.
 *
 * `-v` skips this entirely and lets the tools write straight to the terminal.
 */
final class QuietProcess implements ProcessRunner
{
    private string $output = '';

    public function __construct(private readonly ProcessRunner $inner) {}

    public function run(array $command, string $cwd): int
    {
        $result = $this->inner->capture($command, $cwd);

        $this->output = $result->output;

        return $result->exitCode;
    }

    public function capture(array $command, string $cwd): ProcessResult
    {
        return $this->inner->capture($command, $cwd);
    }

    /**
     * What the last command said, trimmed to the part that explains a failure.
     */
    public function lastOutput(int $lines = 20): string
    {
        return (new ProcessResult(0, $this->output))->tail($lines);
    }
}
