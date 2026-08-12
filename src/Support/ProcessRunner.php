<?php

declare(strict_types=1);

namespace FullSystem\Install\Support;

interface ProcessRunner
{
    /**
     * Runs a command and returns its exit code, streaming output as it goes.
     *
     * The command is an argument list, never a string: nothing reaches a
     * shell, so a value carrying `;` or `$(…)` arrives at the program as one
     * literal argument.
     *
     * @param  list<string>  $command
     */
    public function run(array $command, string $cwd): int;

    /**
     * Runs a command quietly, keeping the output for whoever needs to explain
     * a failure. Verification runs this way: nobody wants a build log on a
     * successful install, and everybody wants it on a broken one.
     *
     * @param  list<string>  $command
     */
    public function capture(array $command, string $cwd): ProcessResult;
}
