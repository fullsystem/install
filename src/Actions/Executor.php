<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\ProcessRunner;
use FullSystem\Install\Support\QuietProcess;
use FullSystem\Install\Support\SystemProcess;
use ReflectionClass;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\spin;

/**
 * Runs a plan, phase by phase, action by action.
 *
 * The first failure stops everything: an action that failed leaves the
 * project in a state the next one was not written for, and carrying on would
 * turn one broken step into several.
 *
 * Commands run quietly unless -v was asked for. Composer alone prints a
 * hundred lines about packages nobody asked about, and the one thing worth
 * reading — what broke — would be buried in the middle of it.
 */
final readonly class Executor
{
    public function __construct(
        private OutputInterface $output,
        private ProcessRunner $processes = new SystemProcess,
    ) {}

    public function phase(Plan $plan, Context $context, string $phase): Result
    {
        $actions = $plan->actions($phase);

        if ($actions === []) {
            return Result::ok();
        }

        $this->output->writeln(['', "  <info>{$phase}</info>"]);

        foreach ($actions as $action) {
            $result = $this->execute($action, $context);

            if (! $result->ok) {
                return Result::fail("{$phase} · {$action->name}: {$result->reason}");
            }
        }

        return Result::ok();
    }

    private function execute(Action $action, Context $context): Result
    {
        $handler = ActionRegistry::handlerFor($action->name);

        if ($handler === null) {
            // Plan::from already refused unknown actions; reaching here means
            // the driver claimed one the package does not ship.
            return Result::fail('no handler for this action');
        }

        // Nothing to hide when nothing runs, and -v means the caller wants the
        // tools' own output.
        if ($context->dryRun || $this->output->isVerbose()) {
            $this->announce($action);

            return $this->report($this->make($handler, $this->processes)->run($context, $action));
        }

        $quiet = new QuietProcess($this->processes);

        $result = spin(
            fn (): Result => $this->make($handler, $quiet)->run($context, $action),
            '  '.trim("{$action->name} {$action->summary()}"),
        );

        $this->announce($action);

        return $result->ok
            ? $this->report($result)
            : Result::fail($this->explain($result, $quiet));
    }

    private function announce(Action $action): void
    {
        $this->output->writeln("    <options=bold>{$action->name}</> {$action->summary()}");
    }

    private function report(Result $result): Result
    {
        if ($result->message !== null && $result->message !== '') {
            $this->output->writeln("      <fg=gray>{$result->message}</>");
        }

        return $result;
    }

    /**
     * A failed command explained itself somewhere in output nobody was shown.
     */
    private function explain(Result $result, QuietProcess $quiet): string
    {
        $output = $quiet->lastOutput();

        return $output === '' ? (string) $result->reason : $result->reason."\n".$output;
    }

    /**
     * Handlers that shell out take the process runner; the rest take nothing.
     *
     * @param  class-string<Handler>  $handler
     */
    private function make(string $handler, ProcessRunner $processes): Handler
    {
        $constructor = (new ReflectionClass($handler))->getConstructor();

        return $constructor?->getNumberOfParameters() > 0
            ? new $handler($processes)
            : new $handler;
    }
}
