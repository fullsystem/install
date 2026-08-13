<?php

declare(strict_types=1);

use FullSystem\Install\Drivers\Laravel\LaravelReact;
use FullSystem\Install\Schema\Schema;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeProcess;
use Tests\Support\FakeRecipeSource;

/**
 * A recipe that says which driver it was written for — which is what makes
 * starting a project possible at all.
 */
function recipeFor(string $driver): FakeRecipeSource
{
    $recipe = tempDirectory();
    touchFile($recipe, 'source/resources/js/app.tsx', 'the recipe app');

    return FakeRecipeSource::returning(
        new Schema('acme/recipe', '1.0.0', [], 'source', $driver),
        $recipe,
    );
}

it('creates the project the recipe was written for', function () {
    $empty = tempDirectory();
    $processes = new FakeProcess;

    cli(['path' => $empty], recipeFor(LaravelReact::NAME), $processes);

    expect($processes->lines()[0])
        ->toBe('composer create-project laravel/react-starter-kit . --no-interaction');
});

/**
 * The tree only exists after the command ran, so detection is asked again
 * rather than assumed — what was installed has to be what the recipe expects.
 */
it('checks the new project is what the driver recognises', function () {
    $empty = tempDirectory();
    // FakeProcess reports success without creating anything, so the directory
    // stays empty and detection fails — which is the case worth catching.
    $tester = cli(['path' => $empty], recipeFor(LaravelReact::NAME), new FakeProcess);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('not what laravel-react recognises');
});

it('stops when the create-project fails', function () {
    $processes = (new FakeProcess)->fails('create-project');

    $tester = cli(['path' => tempDirectory()], recipeFor(LaravelReact::NAME), $processes);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('failed');
});

it('refuses a driver it does not have', function () {
    $tester = cli(['path' => tempDirectory()], recipeFor('laravel-svelte'));

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('laravel-svelte');
});

it('does not touch a directory that has something else in it', function () {
    $occupied = tempDirectory();
    touchFile($occupied, 'index.html', 'someone else lives here');
    $processes = new FakeProcess;

    $tester = cli(['path' => $occupied], recipeFor(LaravelReact::NAME), $processes);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($processes->calls)->toBeEmpty()
        ->and($tester->getDisplay())->toContain('Nothing here looks like a project');
});

it('creates nothing during a dry run', function () {
    $empty = tempDirectory();
    $processes = new FakeProcess;

    $tester = cli(['path' => $empty, '--dry-run' => true], recipeFor(LaravelReact::NAME), $processes);

    expect($processes->calls)->toBeEmpty()
        ->and($tester->getDisplay())->toContain('would run: composer create-project');
});

it('installs normally when the project is already there', function () {
    $processes = new FakeProcess;

    cli(['path' => laravelProject()], recipeFor(LaravelReact::NAME), $processes);

    expect($processes->lines())->not->toContain('composer create-project laravel/react-starter-kit . --no-interaction');
});
