<?php

declare(strict_types=1);

namespace FullSystem\Install\Commands;

use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;

#[AsCommand(
    name: 'install',
    description: 'Replace the Laravel starter kit frontend with your own UI repository.',
    aliases: ['init'],
)]
final class InstallCommand extends Command
{
    public const DEFAULT_REPOSITORY = 'fullsystem/starter-kit';

    protected function configure(): void
    {
        $this->addOption('repository', 'r', InputOption::VALUE_REQUIRED, 'UI repository to install', self::DEFAULT_REPOSITORY);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Prompt::setOutput($output);

        Prompt::interactive($input->isInteractive() && stream_isatty(STDIN));

        $cwd = (string) getcwd();
        $repository = (string) $input->getOption('repository');

        intro('fullsystem/install');

        note("Project:    {$cwd}\nRepository: {$repository}");

        outro('Nothing to do yet.');

        return Command::SUCCESS;
    }
}
