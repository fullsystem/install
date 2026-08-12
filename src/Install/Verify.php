<?php

declare(strict_types=1);

namespace FullSystem\Install\Install;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\ProcessRunner;
use FullSystem\Install\Support\SystemProcess;

/**
 * Proof that what was installed works.
 *
 * Runs last, after post-install, because the commands a theme declares there
 * produce what the build needs — wayfinder writes the route files that the
 * theme's own TypeScript imports, so building before it fails every time.
 *
 * Quiet on success: nobody wants a build log for an install that worked. On
 * failure the tail is printed, because that is the only thing that explains
 * what went wrong.
 */
final readonly class Verify
{
    public function __construct(private ProcessRunner $processes = new SystemProcess) {}

    /**
     * @param  list<array{label: string, command: list<string>}>  $steps
     */
    public function run(Context $context, array $steps): Result
    {
        foreach ($steps as $step) {
            $result = $this->processes->capture($step['command'], $context->cwd);

            if ($result->successful()) {
                continue;
            }

            $tail = $result->tail();

            return Result::fail(
                $step['label'].' failed'.($tail === '' ? '' : ":\n".$tail)
            );
        }

        return Result::ok(count($steps).' check(s) passed');
    }
}
