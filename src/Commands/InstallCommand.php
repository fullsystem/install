<?php

declare(strict_types=1);

namespace FullSystem\Install\Commands;

use FullSystem\Install\Actions\Executor;
use FullSystem\Install\Actions\Plan;
use FullSystem\Install\Checks\Check;
use FullSystem\Install\Context;
use FullSystem\Install\Drivers\Driver;
use FullSystem\Install\Drivers\DriverRegistry;
use FullSystem\Install\Install\CopySource;
use FullSystem\Install\Install\Verify;
use FullSystem\Install\Recipes\DownloadFailed;
use FullSystem\Install\Recipes\FetchedRecipe;
use FullSystem\Install\Recipes\GitHubSource;
use FullSystem\Install\Recipes\InvalidArchive;
use FullSystem\Install\Recipes\InvalidRecipe;
use FullSystem\Install\Recipes\RecipeSource;
use FullSystem\Install\Result;
use FullSystem\Install\Schema\InvalidSchema;
use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\ProcessRunner;
use FullSystem\Install\Support\QuietProcess;
use FullSystem\Install\Support\SystemProcess;
use FullSystem\Install\Workspace;
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

/**
 * `install|init` registers the alias in the same place as the name.
 *
 * The routing in Application decides whether the first token is this
 * command's $path or the name of a different command.
 */
#[AsCommand(
    name: 'install|init',
    description: 'Prepare a project to receive a recipe.',
)]
final class InstallCommand
{
    public const string DEFAULT_RECIPE = 'fullsystem/starter';

    public function __construct(
        private readonly RecipeSource $recipes = new GitHubSource,
        private readonly ProcessRunner $processes = new SystemProcess,
    ) {}

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,

        #[Argument(description: 'Project directory')]
        string $path = '.',

        #[Option(description: 'Recipe to install', shortcut: 'r')]
        string $recipe = self::DEFAULT_RECIPE,

        #[Option(description: 'Answer yes to the risk checks up front')]
        bool $force = false,

        #[Option(description: 'Print the plan without writing anything', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        Prompt::setOutput($output);

        Prompt::interactive($input->isInteractive() && stream_isatty(STDIN));

        intro('fullsystem/install');

        $cwd = $this->directory($path, $dryRun, $output);

        if ($cwd === null) {
            return Command::FAILURE;
        }

        $context = new Context(cwd: $cwd, recipe: $recipe, dryRun: $dryRun, force: $force);

        $registry = DriverRegistry::default();
        $driver = $registry->detect($context);
        $fetched = null;

        // An empty directory has nothing to detect, and what to create is not
        // a guess — the recipe says which driver it was written for. That is
        // the only reason to fetch before knowing the project.
        if ($driver === null) {
            if (! $this->isEmpty($cwd)) {
                error("Nothing here looks like a project I know how to install into: {$cwd}");
                note('Known drivers: '.implode(', ', $registry->names()));

                return Command::FAILURE;
            }

            $fetched = $this->recipe($recipe, $output);

            if ($fetched === null) {
                return Command::FAILURE;
            }

            $driver = $this->startProject($registry, $fetched->schema, $context, $output);

            if ($driver === null) {
                return Command::FAILURE;
            }
        }

        $output->writeln("  <info>✓</info> driver <options=bold>{$driver->name()}</>");

        $fetched ??= $this->recipe($recipe, $output);

        if ($fetched === null) {
            return Command::FAILURE;
        }

        try {
            return $this->withRecipe($fetched, $driver, $context, $output, $cwd, $recipe);
        } finally {
            // Whatever happened, the download does not stay behind.
            $fetched->discard();
        }
    }

    /**
     * @param  string  $cwd  the resolved project directory
     * @param  string  $recipe  what the user asked for, before the recipe named itself
     */
    private function withRecipe(
        FetchedRecipe $fetched,
        Driver $driver,
        Context $context,
        OutputInterface $output,
        string $cwd,
        string $recipe,
    ): int {
        $schema = $fetched->schema;

        note(implode("\n", array_filter([
            "Project: {$cwd}",
            'Recipe:  '.($schema->name ?? $recipe),
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
            // Not the same as nothing to do: the recipe's files still land on
            // the project, which is the one thing every recipe does.
            $output->writeln(['', '  <comment>the recipe declares no actions</comment>']);
        }

        $workspace = new Workspace;

        if (! $context->dryRun) {
            $opened = $workspace->open($context);

            if (! $opened->ok) {
                error((string) $opened->reason);

                return Command::FAILURE;
            }

            $output->writeln([
                '',
                '  <info>✓</info> working on <options=bold>'.Workspace::WORK_BRANCH.'</>, '.
                'branched from <options=bold>'.$workspace->origin().'</>',
            ]);
        }

        $result = $this->install($plan, $context, $output, $driver, $fetched);

        if (! $result->ok) {
            error((string) $result->reason);

            if (! $context->dryRun) {
                $this->rollBack($workspace, $context, $output);
            }

            return Command::FAILURE;
        }

        if ($context->dryRun) {
            outro('Dry run. Nothing was written.');

            return Command::SUCCESS;
        }

        return $this->finish($workspace, $context, $output, $schema);
    }

    /**
     * The work is done and committed on its own branch. Whether it becomes the
     * project is the user's call — and until they make it, the branch they
     * started on is untouched, so the app they had still runs.
     */
    private function finish(Workspace $workspace, Context $context, OutputInterface $output, Schema $schema): int
    {
        $recipe = $schema->name ?? $context->recipe;
        $message = sprintf(
            Workspace::INSTALL_COMMIT,
            $recipe.($schema->version !== null ? " {$schema->version}" : ''),
        );

        if (! $workspace->keep($context, $message)) {
            error('the install worked, but the result could not be committed.');

            return Command::FAILURE;
        }

        $origin = (string) $workspace->origin();

        note(
            "{$recipe} is installed on ".Workspace::WORK_BRANCH.', and it built and tested clean.

'.
            'You are on that branch now, so you can run the app and see it before deciding.
'.
            'Saying no keeps the branch — nothing is lost either way.'
        );

        if (! confirm("Apply it to {$origin}?", default: true)) {
            $workspace->leave($context);

            outro('Left on '.Workspace::WORK_BRANCH.'. Apply it later with: '.$workspace->applyCommand());

            return Command::SUCCESS;
        }

        $applied = $workspace->apply($context, $message);

        if (! $applied->ok) {
            error((string) $applied->reason);

            return Command::FAILURE;
        }

        outro("Applied to {$origin}.");

        return Command::SUCCESS;
    }

    /**
     * An empty directory is a project waiting to exist, and which one is not a
     * guess: the recipe declares the driver it was written for, and the driver
     * knows how to start it.
     *
     * The caller has already established that the directory is empty.
     */
    private function startProject(
        DriverRegistry $registry,
        Schema $schema,
        Context $context,
        OutputInterface $output,
    ): ?Driver {
        if ($schema->driver === null) {
            error("{$context->cwd} is empty, and the recipe does not say which driver it was written for.");

            return null;
        }

        $driver = $registry->get($schema->driver);

        if ($driver === null) {
            error("The recipe was written for a driver I do not have: {$schema->driver}.");
            note('Known drivers: '.implode(', ', $registry->names()));

            return null;
        }

        $step = $driver->newProject();

        if ($step === null) {
            error("{$driver->name()} cannot start a project from nothing.");

            return null;
        }

        if ($context->dryRun) {
            $output->writeln(['', "  <comment>would run: {$step['label']}</comment>"]);

            error("{$context->cwd} is empty, so there is nothing to install into yet.");

            return null;
        }

        note("{$context->cwd} is empty.\nA new project has to be created before {$schema->name} can go in.");

        if (! confirm("Run `{$step['label']}` here?", default: true)) {
            error('Nothing to install into.');

            return null;
        }

        $created = spin(
            fn (): int => (new QuietProcess($this->processes))->run($step['command'], $context->cwd),
            $step['label'],
        );

        if ($created !== 0) {
            error("{$step['label']} failed.");

            return null;
        }

        $output->writeln("  <info>✓</info> created a new {$driver->name()} project");

        // The tree only exists now, so detection is asked again rather than
        // assumed: what was installed has to be what the recipe expects.
        $detected = $registry->detect($context);

        if ($detected === null) {
            error('The new project is not what '.$driver->name().' recognises.');
        }

        return $detected;
    }

    /**
     * Empty enough for a project to be created here. .DS_Store does not count;
     * anything else does.
     */
    private function isEmpty(string $path): bool
    {
        $entries = array_diff(scandir($path) ?: [], ['.', '..', '.DS_Store']);

        return $entries === [];
    }

    /**
     * The directory to install into, created if it is missing.
     *
     * Only the last level: `install ./app` in a directory that exists creates
     * `app`, while `install ./a/b/c` with no `a` is a typo far more often than
     * it is an intention, and building the whole path would hide it.
     */
    private function directory(string $path, bool $dryRun, OutputInterface $output): ?string
    {
        $resolved = realpath($path);

        if ($resolved !== false) {
            if (is_dir($resolved)) {
                return $resolved;
            }

            error("Not a directory: {$path}");

            return null;
        }

        $parent = realpath(dirname($path));

        if ($parent === false || ! is_dir($parent)) {
            error("Cannot create {$path}: there is no ".dirname($path).' to create it in.');

            return null;
        }

        if ($dryRun) {
            $output->writeln("  <comment>would create {$path}</comment>");

            error("{$path} does not exist yet, so there is nothing here to install into.");

            return null;
        }

        if (! mkdir($parent.'/'.basename($path), 0755)) {
            error("Could not create {$path}.");

            return null;
        }

        $output->writeln("  <info>✓</info> created <options=bold>{$path}</>");

        return realpath($path) ?: null;
    }

    /**
     * The whole thing, in the only order it works.
     *
     * pre-install clears the way and installs what the recipe depends on;
     * install lands the files; post-install runs what needs them there —
     * wayfinder reads the routes it just received. Verification is last,
     * because it needs everything the phases before it produced.
     */
    private function install(
        Plan $plan,
        Context $context,
        OutputInterface $output,
        Driver $driver,
        FetchedRecipe $fetched,
    ): Result {
        $executor = new Executor($output, $this->processes);

        $result = $executor->phase($plan, $context, 'pre-install');

        if (! $result->ok) {
            return $result;
        }

        $output->writeln(['', '  <info>install</info>']);
        $output->writeln("    <options=bold>copy</> {$fetched->schema->source}/");

        $copied = (new CopySource)->run($context, $fetched->schema, $fetched->directory);

        if (! $copied->ok) {
            return Result::fail("install · copy: {$copied->reason}");
        }

        $output->writeln("      <fg=gray>{$copied->message}</>");

        $result = $executor->phase($plan, $context, 'post-install');

        if (! $result->ok) {
            return $result;
        }

        return $this->verify($context, $output, $driver);
    }

    /**
     * Quiet unless it fails, which is when its output is the only thing that
     * matters.
     */
    private function verify(Context $context, OutputInterface $output, Driver $driver): Result
    {
        $steps = $driver->verification($context);

        if ($steps === []) {
            return Result::ok();
        }

        $labels = implode(', ', array_column($steps, 'label'));

        $output->writeln(['', '  <info>verify</info>']);

        if ($context->dryRun) {
            $output->writeln("    <fg=gray>would run: {$labels}</>");

            return Result::ok();
        }

        $result = spin(
            fn (): Result => (new Verify($this->processes))->run($context, $steps),
            $labels,
        );

        if ($result->ok) {
            $output->writeln("    <fg=gray>{$result->message}</>");
        }

        return $result;
    }

    /**
     * A failed run leaves the project mid-transformation, which is worse than
     * either end of it. Nothing is ever pushed, so this only ever undoes what
     * happened locally.
     */
    private function rollBack(Workspace $workspace, Context $context, OutputInterface $output): void
    {
        $output->writeln('');

        if ($workspace->restore($context)) {
            $output->writeln('  <comment>rolled back — the project is as you left it</comment>');

            return;
        }

        $output->writeln('  <comment>could not roll back. Undo it by hand: '.$workspace->undoCommand().'</comment>');
    }

    /**
     * The recipe, or null with the reason already reported.
     */
    private function recipe(string $recipe, OutputInterface $output): ?FetchedRecipe
    {
        try {
            return spin(fn (): FetchedRecipe => $this->recipes->fetch($recipe), "Fetching {$recipe}");
        } catch (InvalidRecipe|DownloadFailed|InvalidArchive|InvalidSchema $exception) {
            error($exception->getMessage());

            return null;
        }
    }

    /**
     * The checks the driver always runs, plus the ones this recipe asked for.
     *
     * A recipe naming a check the driver does not offer is refused here, with
     * the same reasoning as an unknown action: better to stop before the run
     * than to quietly not check what the recipe said it depends on.
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
                    "The recipe requires a check this driver does not offer: {$name}. ".
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
