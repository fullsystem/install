<?php

declare(strict_types=1);

namespace FullSystem\Install\Commands;

use FullSystem\Install\Context;
use FullSystem\Install\Drivers\Driver;
use FullSystem\Install\Drivers\DriverRegistry;
use FullSystem\Install\Result;
use FullSystem\Install\Steps\Step;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

/**
 * `install|init` registers the alias in the same place as the name.
 *
 * The routing in Application decides whether the first token is this
 * command's $path or the name of a different command.
 */
#[AsCommand(
    name: 'install|init',
    description: 'Prepare a Laravel project to receive a theme.',
)]
final class InstallCommand
{
    public const string DEFAULT_THEME = 'fullsystem/starter';

    /** The phases, in the only order they make sense. */
    private const array PHASES = ['pre-install', 'install', 'post-install'];

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,

        #[Argument(description: 'Project directory')]
        string $path = '.',

        #[Option(description: 'Theme to install', shortcut: 't')]
        string $theme = self::DEFAULT_THEME,

        #[Option(description: 'Answer yes to the risk checks up front')]
        bool $force = false,
    ): int {
        Prompt::setOutput($output);

        Prompt::interactive($input->isInteractive() && stream_isatty(STDIN));

        intro('fullsystem/install');

        warning('Under development. Expect breaking changes between versions.');

        $cwd = realpath($path);

        if ($cwd === false || ! is_dir($cwd)) {
            error("Not a directory: {$path}");

            return Command::FAILURE;
        }

        $context = new Context(cwd: $cwd, theme: $theme, force: $force);

        $registry = DriverRegistry::default();
        $driver = $registry->detect($context);

        if ($driver === null) {
            error("Nothing here looks like a project I know how to install into: {$cwd}");
            note('Known drivers: '.implode(', ', $registry->names()));

            return Command::FAILURE;
        }

        note("Project: {$cwd}\nTheme:   {$theme}\nDriver:  {$driver->name()}");

        if (! $this->passesChecks($driver, $context, $output)) {
            return Command::FAILURE;
        }

        return $this->runPhases($driver, $context, $output);
    }

    /**
     * A failing check is either a verdict or a question.
     *
     * The ones describing what the command needs to work at all stop the run.
     * The ones describing risk are asked, and no is an answer — with --force
     * standing in for a yes given up front, which is also what happens when
     * there is no terminal to ask in.
     */
    private function passesChecks(Driver $driver, Context $context, OutputInterface $output): bool
    {
        foreach ($driver->checks() as $check) {
            $result = $check->run($context);

            if ($result->ok) {
                $output->writeln("  <info>✓</info> {$check->name()}");

                continue;
            }

            $reason = "{$check->name()}: {$result->reason}";

            if (! $check->forceable()) {
                error($reason);

                return false;
            }

            if ($context->force) {
                $output->writeln("  <comment>! {$reason} — continuing because of --force</comment>");

                continue;
            }

            if (! confirm("{$reason}. Continue anyway?", default: false)) {
                error($reason);

                return false;
            }
        }

        return true;
    }

    /**
     * Each phase runs to completion before the next one starts, and the first
     * failure stops everything. Recovery belongs to the step that knows
     * whether the result works, not here.
     */
    private function runPhases(Driver $driver, Context $context, OutputInterface $output): int
    {
        $ran = 0;

        foreach (self::PHASES as $phase) {
            foreach ($this->stepsFor($driver, $phase) as $step) {
                $output->writeln(['', "  <info>→ {$step->name()}</info>"]);

                $result = $step->run($context);
                $ran++;

                if (! $result->ok) {
                    error("{$phase} · {$step->name()}: {$result->reason}");

                    return Command::FAILURE;
                }
            }
        }

        if ($ran === 0) {
            outro("No steps are wired to {$driver->name()} yet.");

            return Command::SUCCESS;
        }

        outro('Done.');

        return Command::SUCCESS;
    }

    /**
     * @return list<Step>
     */
    private function stepsFor(Driver $driver, string $phase): array
    {
        return match ($phase) {
            'pre-install' => $driver->preInstall(),
            'install' => $driver->install(),
            'post-install' => $driver->postInstall(),
            default => [],
        };
    }
}
