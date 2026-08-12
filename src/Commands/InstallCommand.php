<?php

declare(strict_types=1);

namespace FullSystem\Install\Commands;

use FullSystem\Install\Actions\Executor;
use FullSystem\Install\Actions\Plan;
use FullSystem\Install\Checks\Check;
use FullSystem\Install\Context;
use FullSystem\Install\Drivers\Driver;
use FullSystem\Install\Drivers\DriverRegistry;
use FullSystem\Install\RestorePoint;
use FullSystem\Install\Schema\InvalidSchema;
use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\ProcessRunner;
use FullSystem\Install\Support\SystemProcess;
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

    public function __construct(
        private readonly ThemeSource $themes = new GitHubSource,
        private readonly ProcessRunner $processes = new SystemProcess,
    ) {}

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
            $checks = $this->checksFor($driver, $schema);
        } catch (InvalidSchema $exception) {
            error($exception->getMessage());

            return Command::FAILURE;
        }

        if (! $this->passesChecks($checks, $context, $output)) {
            return Command::FAILURE;
        }

        if ($plan->isEmpty()) {
            outro('The theme declares no actions.');

            return Command::SUCCESS;
        }

        $point = new RestorePoint;

        if (! $context->dryRun) {
            $established = $point->establish($context);

            if (! $established->ok) {
                error((string) $established->reason);

                return Command::FAILURE;
            }

            $output->writeln('  <info>✓</info> restore point at <options=bold>'.RestorePoint::NAME.'</>');
        }

        $result = (new Executor($output, $this->processes))->run($plan, $context);

        if (! $result->ok) {
            error((string) $result->reason);

            if (! $context->dryRun) {
                $this->rollBack($point, $context, $output);
            }

            return Command::FAILURE;
        }

        if ($context->dryRun) {
            outro('Dry run. Nothing was written.');

            return Command::SUCCESS;
        }

        note("Review with:  git diff\nUndo it all:  ".$point->undoCommand());

        outro('Done — but copying the theme files is not wired yet.');

        return Command::SUCCESS;
    }

    /**
     * A failed run leaves the project mid-transformation, which is worse than
     * either end of it. Nothing is ever pushed, so this only ever undoes what
     * happened locally.
     */
    private function rollBack(RestorePoint $point, Context $context, OutputInterface $output): void
    {
        $output->writeln('');

        if ($point->restore($context)) {
            $output->writeln('  <comment>rolled back to '.RestorePoint::NAME.'</comment>');

            return;
        }

        $output->writeln('  <comment>could not roll back. Undo it by hand: '.$point->undoCommand().'</comment>');
    }

    private function fetch(string $theme): Schema
    {
        return spin(fn (): Schema => $this->themes->fetch($theme), "Fetching {$theme}");
    }

    /**
     * The checks the driver always runs, plus the ones this theme asked for.
     *
     * A theme naming a check the driver does not offer is refused here, with
     * the same reasoning as an unknown action: better to stop before the run
     * than to quietly not check what the theme said it depends on.
     *
     * @return list<Check>
     */
    private function checksFor(Driver $driver, Schema $schema): array
    {
        $available = [];

        foreach ($driver->optionalChecks() as $check) {
            $available[$check->name()] = $check;
        }

        $required = [];

        foreach ($schema->requires as $name) {
            if (! isset($available[$name])) {
                throw new InvalidSchema(
                    "The theme requires a check this driver does not offer: {$name}. ".
                    ($available === []
                        ? 'This driver offers none.'
                        : 'It offers: '.implode(', ', array_keys($available)).'.')
                );
            }

            $required[] = $available[$name];
        }

        return [...$driver->checks(), ...$required];
    }

    /**
     * A failing check is either a verdict or a question.
     *
     * The ones describing what the command needs to work at all stop the run.
     * The ones describing risk are asked, and no is an answer — with --force
     * standing in for a yes given up front, which is also what happens when
     * there is no terminal to ask in.
     *
     * @param  list<Check>  $checks
     */
    private function passesChecks(array $checks, Context $context, OutputInterface $output): bool
    {
        foreach ($checks as $check) {
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
