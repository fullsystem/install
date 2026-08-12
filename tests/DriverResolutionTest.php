<?php

declare(strict_types=1);

use FullSystem\Install\Drivers\Laravel\LaravelReact;
use Symfony\Component\Console\Command\Command;

it('reports the driver it detected', function () {
    expect(cli(['path' => laravelProject()])->getDisplay())->toContain(LaravelReact::NAME);
});

it('stops when no driver recognises the project', function () {
    $empty = sys_get_temp_dir().'/fullsystem-empty-'.bin2hex(random_bytes(6));
    mkdir($empty);

    $tester = cli(['path' => $empty]);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain(LaravelReact::NAME); // lists what it does know

    rmdir($empty);
});

it('does not mistake a laravel project without the react adapter', function () {
    $path = laravelProject();
    file_put_contents($path.'/package.json', '{"dependencies":{"@inertiajs/vue3":"^3.6.1"}}');

    expect(cli(['path' => $path])->getStatusCode())->toBe(Command::FAILURE);
});

it('says that nothing executes yet', function () {
    expect(cli(['path' => laravelProject()])->getDisplay())->toContain('No action runs yet');
});
