<?php

declare(strict_types=1);

use FullSystem\Install\Schema\Schema;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeProcess;
use Tests\Support\FakeRecipeSource;

function noisyRecipe(): FakeRecipeSource
{
    return FakeRecipeSource::returning(new Schema('acme/recipe', '1.0.0', [
        'pre-install' => [['composer' => ['laravel/reverb']]],
    ]));
}

/** What composer says while it works, and nobody needs to read. */
const COMPOSER_NOISE = "Loading composer repositories\nUpdating dependencies\n  - Locking react/dns (v1.14.0)\nWriting lock file";

it('keeps the tools quiet when they succeed', function () {
    $processes = (new FakeProcess)->outputs(COMPOSER_NOISE);

    $display = cli(['path' => laravelProject()], noisyRecipe(), $processes)->getDisplay();

    expect($display)->not->toContain('Locking react/dns')
        ->and($display)->not->toContain('Writing lock file')
        ->and($display)->toContain('composer');
});

it('shows what the tool said when it fails', function () {
    $processes = (new FakeProcess)
        ->fails('composer require')
        ->outputs(COMPOSER_NOISE."\nProblem 1\n  - Root composer.json requires acme/nope, it could not be found.");

    $tester = cli(['path' => laravelProject()], noisyRecipe(), $processes);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('could not be found');
});

it('lets the tools speak when -v is asked for', function () {
    $processes = (new FakeProcess)->outputs(COMPOSER_NOISE);

    // -v makes the executor hand the handler the real runner, which streams
    // straight to the terminal rather than being captured.
    $tester = cli(['path' => laravelProject(), '-v' => true], noisyRecipe(), $processes);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS);
});
