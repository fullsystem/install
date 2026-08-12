<?php

declare(strict_types=1);

use FullSystem\Install\Application;
use FullSystem\Install\Commands\InstallCommand;
use FullSystem\Install\Themes\ThemeSource;
use Symfony\Component\Console\Tester\ApplicationTester;
use Tests\Support\FakeThemeSource;

/**
 * Runs the CLI the way cpx does — through the application, not the command —
 * so the default command resolution is covered too.
 *
 * @param  list<string>  $argv
 */
/**
 * An empty directory under the system temp dir, removed after the test.
 */
function tempDirectory(): string
{
    $path = sys_get_temp_dir().'/fullsystem-test-'.bin2hex(random_bytes(6));

    mkdir($path, 0755, true);

    register_shutdown_function(static function () use ($path): void {
        is_dir($path) && exec('rm -rf '.escapeshellarg($path));
    });

    return $path;
}

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

function cli(array $argv = [], ?ThemeSource $themes = null): ApplicationTester
{
    // Never the real GitHub: tests must not depend on the network, and the
    // command's job here is what it does with the schema, not how it got it.
    $application = new Application(new InstallCommand($themes ?? FakeThemeSource::returning()));
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
