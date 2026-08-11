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

    $tester = new ApplicationTester($application);
    $tester->run($argv, ['interactive' => false]);

    return $tester;
}
