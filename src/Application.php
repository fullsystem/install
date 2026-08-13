<?php

declare(strict_types=1);

namespace FullSystem\Install;

use FullSystem\Install\Commands\InstallCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Application extends ConsoleApplication
{
    public const string VERSION = '2.1.4';

    private const string DEFAULT_COMMAND = 'install';

    /**
     * The command is injectable so tests can hand it a recipe source that does
     * not reach the network.
     */
    public function __construct(?InstallCommand $install = null)
    {
        parent::__construct('fullsystem/install', self::VERSION);

        $this->addCommand($install ?? new InstallCommand);

        $this->setDefaultCommand(self::DEFAULT_COMMAND);
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        return parent::run($input ?? $this->routedInput(), $output);
    }

    /**
     * Decides whether the first token names a command or is an argument for
     * the installer.
     *
     * `cpx fullsystem/install .` and `cpx fullsystem/install login` differ
     * only in that token, and Symfony reads the first argument as a command
     * name unconditionally — so the choice has to be made before it sees the
     * input, not after.
     *
     * A directory that happens to share a name with a command loses to the
     * command; `./login` is how you say you meant the directory.
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    public function route(array $tokens): array
    {
        $first = $tokens[0] ?? null;

        if ($first === null || str_starts_with($first, '-') || ! $this->has($first)) {
            return [self::DEFAULT_COMMAND, ...$tokens];
        }

        return $tokens;
    }

    private function routedInput(): ArgvInput
    {
        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];
        $script = array_shift($argv);

        return new ArgvInput([$script ?? 'fullsystem', ...$this->route($argv)]);
    }
}
