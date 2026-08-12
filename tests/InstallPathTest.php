<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;

it('installs into the current directory by default', function () {
    expect(cli([])->getDisplay())->toContain((string) getcwd());
});

it('accepts a directory as its argument', function () {
    $path = sys_get_temp_dir().'/fullsystem-'.bin2hex(random_bytes(4));
    mkdir($path);

    $tester = cli(['path' => $path]);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain((string) realpath($path));

    rmdir($path);
});

it('resolves a relative path', function () {
    expect(cli(['path' => '.'])->getDisplay())->toContain((string) getcwd());
});

it('fails when the directory does not exist', function () {
    $tester = cli(['path' => '/no/such/directory']);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('/no/such/directory');
});

it('fails when the path is a file', function () {
    $file = tempnam(sys_get_temp_dir(), 'fullsystem');

    expect(cli(['path' => $file])->getStatusCode())->toBe(Command::FAILURE);

    unlink($file);
});
