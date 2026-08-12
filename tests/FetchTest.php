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

it('does not fetch when the project has no driver', function () {
    $source = FakeThemeSource::returning();
    $empty = tempDirectory();

    $tester = cli(['path' => $empty], $source);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($source->asked)->toBeEmpty();
});
