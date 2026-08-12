<?php

declare(strict_types=1);

use FullSystem\Install\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Runs the CLI the way cpx does — through the application, not the command —
 * so the default command resolution is covered too.
 *
 * @param  list<string>  $argv
 */
function cli(array $argv = []): ApplicationTester
{
    $application = new Application;
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);

    // ApplicationTester takes structured input, so it never passes through
    // Application::route() — that path is covered by RoutingTest. Naming the
    // command here is what the routing would have done with real argv.
    // ArrayInput::getFirstArgument() reads the array in order, so the command
    // has to come first or Symfony reads an argument as the command name.
    $command = $argv['command'] ?? 'install';
    unset($argv['command']);

    $tester = new ApplicationTester($application);
    $tester->run(array_merge(['command' => $command], $argv), ['interactive' => false]);

    return $tester;
}
