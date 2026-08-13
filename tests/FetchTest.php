<?php

declare(strict_types=1);

use FullSystem\Install\Recipes\DownloadFailed;
use FullSystem\Install\Recipes\InvalidArchive;
use FullSystem\Install\Recipes\InvalidRecipe;
use FullSystem\Install\Schema\InvalidSchema;
use FullSystem\Install\Schema\Schema;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeRecipeSource;

it('asks the source for the recipe it was given', function () {
    $source = FakeRecipeSource::returning();

    cli(['path' => laravelProject(), '--recipe' => 'acme/recipe'], $source);

    expect($source->asked)->toBe(['acme/recipe']);
});

it('reports what the recipe declares', function () {
    $source = FakeRecipeSource::returning(new Schema('acme/recipe', '2.1.0', [
        'pre-install' => [['composer' => ['laravel/reverb']]],
        'post-install' => [['artisan' => ['migrate']]],
    ]));

    $display = cli(['path' => laravelProject()], $source)->getDisplay();

    expect($display)->toContain('acme/recipe')
        ->and($display)->toContain('2.1.0')
        ->and($display)->toContain('pre-install')
        ->and($display)->toContain('composer')
        ->and($display)->toContain('post-install')
        ->and($display)->toContain('artisan');
});

it('says so when the recipe declares nothing', function () {
    $source = FakeRecipeSource::returning(new Schema('acme/recipe', null, []));

    expect(cli(['path' => laravelProject()], $source)->getDisplay())
        ->toContain('declares no actions');
});

it('stops on anything that goes wrong while fetching', function (RuntimeException $failure) {
    $tester = cli(['path' => laravelProject()], FakeRecipeSource::failing($failure));

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain($failure->getMessage());
})->with([
    'bad name' => fn () => new InvalidRecipe('A recipe is owner/repository — got: https://evil.test/x'),
    'unreachable' => fn () => new DownloadFailed('No such recipe, or it is not public: acme/nope'),
    'hostile archive' => fn () => new InvalidArchive('The archive contains an entry that escapes the destination: ../../etc'),
    'no schema' => fn () => new InvalidSchema('The recipe has no schema.json.'),
]);

/**
 * The project is looked at first, and the recipe is only fetched once there is
 * something to do with it. Pointing this at the wrong directory should not
 * cost a download.
 */
it('does not fetch when the directory holds something it cannot install into', function () {
    $wrong = tempDirectory();
    touchFile($wrong, 'index.html', 'not laravel');

    $source = FakeRecipeSource::returning();

    $tester = cli(['path' => $wrong], $source);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($source->asked)->toBeEmpty();
});

/**
 * An empty directory is the exception: there is nothing to detect, and the
 * recipe is what says which project to create.
 */
it('fetches to find out what project an empty directory needs', function () {
    $source = FakeRecipeSource::returning();

    cli(['path' => tempDirectory()], $source);

    expect($source->asked)->not->toBeEmpty();
});

it('fetches once, not twice', function () {
    $source = FakeRecipeSource::returning();

    cli(['path' => laravelProject()], $source);

    expect($source->asked)->toHaveCount(1);
});
