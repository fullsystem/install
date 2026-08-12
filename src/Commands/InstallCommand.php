<?php

declare(strict_types=1);

namespace FullSystem\Install\Commands;

use FullSystem\Install\Actions\Plan;
use FullSystem\Install\Context;
use FullSystem\Install\Drivers\Driver;
use FullSystem\Install\Drivers\DriverRegistry;
use FullSystem\Install\Schema\InvalidSchema;
use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Themes\DownloadFailed;
use FullSystem\Install\Themes\GitHubSource;
use FullSystem\Install\Themes\InvalidArchive;
use FullSystem\Install\Themes\InvalidTheme;
use FullSystem\Install\Themes\ThemeSource;
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
use function Laravel\Prompts\spin;
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

    public function __construct(private readonly ThemeSource $themes = new GitHubSource) {}

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,

        #[Argument(description: 'Project directory')]
        string $path = '.',

        #[Option(description: 'Theme to install', shortcut: 't')]
        string $theme = self::DEFAULT_THEME,

        #[Option(description: 'Answer yes to the risk checks up front')]
        bool $force = false,

        #[Option(description: 'Print the plan without writing anything', name: 'dry-run')]
        bool $dryRun = false,
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

        $context = new Context(cwd: $cwd, theme: $theme, dryRun: $dryRun, force: $force);

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

        try {
            $schema = $this->fetch($theme);
        } catch (InvalidTheme|DownloadFailed|InvalidArchive|InvalidSchema $exception) {
            error($exception->getMessage());

            return Command::FAILURE;
        }

        note(implode("\n", array_filter([
            'Theme:   '.($schema->name ?? $theme),
            $schema->version !== null ? "Version: {$schema->version}" : null,
        ])));

        try {
            $plan = Plan::from($schema, $driver->actions());
        } catch (InvalidSchema $exception) {
            error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->showPlan($plan, $output);

        if ($context->dryRun) {
            outro('Dry run. Nothing was written.');

            return Command::SUCCESS;
        }

        outro('No action runs yet.');

        return Command::SUCCESS;
    }

    /**
     * The plan is printed on every run, not only with --dry-run: this command
     * deletes files, and what it is about to do should never be a surprise.
     */
    private function showPlan(Plan $plan, OutputInterface $output): void
    {
        if ($plan->isEmpty()) {
            $output->writeln(['', '  <comment>The theme declares no actions.</comment>']);

            return;
        }

        foreach (Plan::PHASES as $phase) {
            $actions = $plan->actions($phase);

            if ($actions === []) {
                continue;
            }

            $output->writeln(['', "  <info>{$phase}</info>"]);

            foreach ($actions as $index => $action) {
                $output->writeln(sprintf('    %d. %-9s %s', $index + 1, $action->name, $action->summary()));
            }
        }

        $output->writeln('');
    }

    private function fetch(string $theme): Schema
    {
        return spin(fn (): Schema => $this->themes->fetch($theme), "Fetching {$theme}");
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
}
