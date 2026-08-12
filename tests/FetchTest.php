<?php

declare(strict_types=1);

use FullSystem\Install\Schema\InvalidSchema;
use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Themes\DownloadFailed;
use FullSystem\Install\Themes\InvalidArchive;
use FullSystem\Install\Themes\InvalidTheme;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeThemeSource;

it('asks the source for the theme it was given', function () {
    $source = FakeThemeSource::returning();

    cli(['path' => laravelProject(), '--theme' => 'acme/theme'], $source);

    expect($source->asked)->toBe(['acme/theme']);
});

it('reports what the theme declares', function () {
    $source = FakeThemeSource::returning(new Schema('acme/theme', '2.1.0', [
        'pre-install' => [['composer' => ['laravel/reverb']]],
        'post-install' => [['artisan' => ['migrate']]],
    ]));

    $display = cli(['path' => laravelProject()], $source)->getDisplay();

    expect($display)->toContain('acme/theme')
        ->and($display)->toContain('2.1.0')
        ->and($display)->toContain('pre-install')
        ->and($display)->toContain('composer')
        ->and($display)->toContain('post-install')
        ->and($display)->toContain('artisan');
});

it('says so when the theme declares nothing', function () {
    $source = FakeThemeSource::returning(new Schema('acme/theme', null, []));

    expect(cli(['path' => laravelProject()], $source)->getDisplay())
        ->toContain('declares no actions');
});

it('stops on anything that goes wrong while fetching', function (RuntimeException $failure) {
    $tester = cli(['path' => laravelProject()], FakeThemeSource::failing($failure));

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain($failure->getMessage());
})->with([
    'bad name' => fn () => new InvalidTheme('A theme is owner/repository — got: https://evil.test/x'),
    'unreachable' => fn () => new DownloadFailed('No such theme, or it is not public: acme/nope'),
    'hostile archive' => fn () => new InvalidArchive('The archive contains an entry that escapes the destination: ../../etc'),
    'no schema' => fn () => new InvalidSchema('The theme has no schema.json.'),
]);

/**
 * The project is looked at first, and the theme is only fetched once there is
 * something to do with it. Pointing this at the wrong directory should not
 * cost a download.
 */
it('does not fetch when the directory holds something it cannot install into', function () {
    $wrong = tempDirectory();
    touchFile($wrong, 'index.html', 'not laravel');

    $source = FakeThemeSource::returning();

    $tester = cli(['path' => $wrong], $source);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($source->asked)->toBeEmpty();
});

/**
 * An empty directory is the exception: there is nothing to detect, and the
 * theme is what says which project to create.
 */
it('fetches to find out what project an empty directory needs', function () {
    $source = FakeThemeSource::returning();

    cli(['path' => tempDirectory()], $source);

    expect($source->asked)->not->toBeEmpty();
});

it('fetches once, not twice', function () {
    $source = FakeThemeSource::returning();

    cli(['path' => laravelProject()], $source);

    expect($source->asked)->toHaveCount(1);
});
