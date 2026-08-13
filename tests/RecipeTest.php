<?php

declare(strict_types=1);

use FullSystem\Install\Recipes\InvalidRecipe;
use FullSystem\Install\Recipes\Recipe;

it('parses owner/repo', function () {
    $recipe = Recipe::fromString('fullsystem/starter');

    expect($recipe->owner)->toBe('fullsystem')
        ->and($recipe->repository)->toBe('starter')
        ->and((string) $recipe)->toBe('fullsystem/starter');
});

it('builds the archive url for the default branch', function () {
    expect(Recipe::fromString('fullsystem/starter')->archiveUrl())
        ->toBe('https://github.com/fullsystem/starter/archive/refs/heads/main.zip');
});

it('accepts the names real repositories use', function (string $name) {
    expect(Recipe::fromString($name)->owner)->not->toBeEmpty();
})->with([
    'laravel/starter-kit',
    'baconfy/ui',
    'some-org/some.repo',
    'org123/repo_name',
]);

/**
 * The recipe name goes into a URL. Anything that is not exactly owner/repo is
 * refused before it can steer the request somewhere else.
 */
it('refuses anything that is not owner/repo', function (string $name) {
    Recipe::fromString($name);
})->throws(InvalidRecipe::class)->with([
    '',
    'starter',
    'fullsystem/starter/extra',
    '../../etc/passwd',
    'https://evil.test/repo',
    'fullsystem/starter?ref=x',
    'fullsystem/starter#fragment',
    'full system/starter',
    '-flag/repo',
    'fullsystem/../other',
]);
