<?php

declare(strict_types=1);

use FullSystem\Install\Drivers\Laravel\LaravelReact;
use Symfony\Component\Console\Command\Command;

it('reports the driver it detected', function () {
    expect(cli(['path' => laravelProject()])->getDisplay())->toContain(LaravelReact::NAME);
});

it('stops when a directory has the wrong thing in it', function () {
    $wrong = tempDirectory();
    touchFile($wrong, 'index.html', '<h1>not laravel</h1>');

    $tester = cli(['path' => $wrong]);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain(LaravelReact::NAME); // lists what it does know
});

it('cannot start a project when the recipe does not name a driver', function () {
    $tester = cli(['path' => tempDirectory()]);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('does not say which driver');
});

it('does not mistake a laravel project without the react adapter', function () {
    $path = laravelProject();
    file_put_contents($path.'/package.json', '{"dependencies":{"@inertiajs/vue3":"^3.6.1"}}');

    expect(cli(['path' => $path])->getStatusCode())->toBe(Command::FAILURE);
});

it('says so when the recipe has nothing to run', function () {
    expect(cli(['path' => laravelProject()])->getDisplay())->toContain('declares no actions');
});
