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
/**
 * The smallest tree LaravelReact::detect() recognises: artisan and
 * composer.json for the backend, the Inertia adapter for the variant.
 */
function laravelProject(): string
{
    $path = sys_get_temp_dir().'/fullsystem-test-'.bin2hex(random_bytes(6));

    mkdir($path, 0755, true);
    file_put_contents($path.'/artisan', '');
    file_put_contents($path.'/composer.json', '{}');
    file_put_contents($path.'/package.json', '{"dependencies":{"@inertiajs/react":"^3.6.1"}}');

    register_shutdown_function(static function () use ($path): void {
        is_dir($path) && exec('rm -rf '.escapeshellarg($path));
    });

    return $path;
}

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
