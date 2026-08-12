<?php

declare(strict_types=1);

use FullSystem\Install\Drivers\Laravel\LaravelReact;
use FullSystem\Install\Schema\Schema;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeProcess;
use Tests\Support\FakeThemeSource;

/**
 * A theme that says which driver it was written for — which is what makes
 * starting a project possible at all.
 */
function themeFor(string $driver): FakeThemeSource
{
    $theme = tempDirectory();
    touchFile($theme, 'source/resources/js/app.tsx', 'the theme app');

    return FakeThemeSource::returning(
        new Schema('acme/theme', '1.0.0', [], 'source', $driver),
        $theme,
    );
}

it('creates the project the theme was written for', function () {
    $empty = tempDirectory();
    $processes = new FakeProcess;

    cli(['path' => $empty], themeFor(LaravelReact::NAME), $processes);

    expect($processes->lines()[0])
        ->toBe('composer create-project laravel/react-starter-kit . --no-interaction');
});

/**
 * The tree only exists after the command ran, so detection is asked again
 * rather than assumed — what was installed has to be what the theme expects.
 */
it('checks the new project is what the driver recognises', function () {
    $empty = tempDirectory();
    // FakeProcess reports success without creating anything, so the directory
    // stays empty and detection fails — which is the case worth catching.
    $tester = cli(['path' => $empty], themeFor(LaravelReact::NAME), new FakeProcess);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('not what laravel-react recognises');
});

it('stops when the create-project fails', function () {
    $processes = (new FakeProcess)->fails('create-project');

    $tester = cli(['path' => tempDirectory()], themeFor(LaravelReact::NAME), $processes);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('failed');
});

it('refuses a driver it does not have', function () {
    $tester = cli(['path' => tempDirectory()], themeFor('laravel-svelte'));

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('laravel-svelte');
});

it('does not touch a directory that has something else in it', function () {
    $occupied = tempDirectory();
    touchFile($occupied, 'index.html', 'someone else lives here');
    $processes = new FakeProcess;

    $tester = cli(['path' => $occupied], themeFor(LaravelReact::NAME), $processes);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($processes->calls)->toBeEmpty()
        ->and($tester->getDisplay())->toContain('Nothing here looks like a project');
});

it('creates nothing during a dry run', function () {
    $empty = tempDirectory();
    $processes = new FakeProcess;

    $tester = cli(['path' => $empty, '--dry-run' => true], themeFor(LaravelReact::NAME), $processes);

    expect($processes->calls)->toBeEmpty()
        ->and($tester->getDisplay())->toContain('would run: composer create-project');
});

it('installs normally when the project is already there', function () {
    $processes = new FakeProcess;

    cli(['path' => laravelProject()], themeFor(LaravelReact::NAME), $processes);

    expect($processes->lines())->not->toContain('composer create-project laravel/react-starter-kit . --no-interaction');
});
