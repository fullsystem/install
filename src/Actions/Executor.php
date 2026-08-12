<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\ProcessRunner;
use FullSystem\Install\Support\SystemProcess;
use ReflectionClass;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs a plan, phase by phase, action by action.
 *
 * The first failure stops everything: an action that failed leaves the
 * project in a state the next one was not written for, and carrying on would
 * turn one broken step into several.
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

        $this->output->writeln("    <options=bold>{$action->name}</> {$action->summary()}");

        $result = $this->make($handler)->run($context, $action);

        if ($result->message !== null && $result->message !== '') {
            $this->output->writeln("      <fg=gray>{$result->message}</>");
        }

        return $result;
    }

    /**
     * Handlers that shell out take the process runner; the rest take nothing.
     * Passing it through here is what keeps tests from running composer for
     * real.
     *
     * @param  class-string<Handler>  $handler
     */
    private function make(string $handler): Handler
    {
        $constructor = (new ReflectionClass($handler))->getConstructor();

        return $constructor?->getNumberOfParameters() > 0
            ? new $handler($this->processes)
            : new $handler;
    }
}
