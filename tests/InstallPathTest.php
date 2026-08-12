<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;

it('accepts a directory as its argument', function () {
    $path = laravelProject();

    $tester = cli(['path' => $path]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain((string) realpath($path));
});

it('resolves a relative path', function () {
    $path = laravelProject();

    $tester = cli(['path' => $path.'/../'.basename($path)]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain((string) realpath($path));
});

it('fails when the directory does not exist', function () {
    $tester = cli(['path' => '/no/such/directory']);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('/no/such/directory');
});

it('fails when the path is a file', function () {
    $file = (string) tempnam(sys_get_temp_dir(), 'fullsystem');

    expect(cli(['path' => $file])->getStatusCode())->toBe(Command::FAILURE);

    unlink($file);
});
