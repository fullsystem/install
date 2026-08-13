<?php

declare(strict_types=1);

use FullSystem\Install\Commands\InstallCommand;
use Symfony\Component\Console\Command\Command;

it('runs the installer when no command is given', function () {
    expect(cli(['path' => laravelProject()])->getDisplay())->toContain('fullsystem/install');
});

it('still answers to the init alias', function () {
    $tester = cli(['command' => 'init', 'path' => laravelProject()]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
});

it('installs fullsystem/starter by default', function () {
    expect(cli(['path' => laravelProject()])->getDisplay())
        ->toContain(InstallCommand::DEFAULT_THEME);
});

it('accepts another theme', function () {
    expect(cli(['path' => laravelProject(), '--theme' => 'laravel/starter-kit'])->getDisplay())
        ->toContain('laravel/starter-kit');
});

it('accepts the -t shortcut', function () {
    expect(cli(['path' => laravelProject(), '-t' => 'laravel/starter-kit'])->getDisplay())
        ->toContain('laravel/starter-kit');
});
