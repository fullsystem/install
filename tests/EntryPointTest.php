<?php

declare(strict_types=1);

use FullSystem\Install\Commands\InstallCommand;
use Symfony\Component\Console\Command\Command;

it('runs the installer when no command is given', function () {
    $tester = cli([]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('fullsystem/install');
});

it('still answers to the init alias', function () {
    expect(cli(['command' => 'init'])->getStatusCode())->toBe(Command::SUCCESS);
});

it('installs fullsystem/starter-kit by default', function () {
    expect(cli([])->getDisplay())->toContain(InstallCommand::DEFAULT_REPOSITORY);
});

it('accepts another repository', function () {
    expect(cli(['--repository' => 'acme/theme'])->getDisplay())->toContain('acme/theme');
});
