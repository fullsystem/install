<?php

declare(strict_types=1);

use FullSystem\Install\Themes\InvalidTheme;
use FullSystem\Install\Themes\Theme;

it('parses owner/repo', function () {
    $theme = Theme::fromString('fullsystem/starter');

    expect($theme->owner)->toBe('fullsystem')
        ->and($theme->repository)->toBe('starter')
        ->and((string) $theme)->toBe('fullsystem/starter');
});

it('builds the archive url for the default branch', function () {
    expect(Theme::fromString('fullsystem/starter')->archiveUrl())
        ->toBe('https://github.com/fullsystem/starter/archive/refs/heads/main.zip');
});

it('accepts the names real repositories use', function (string $name) {
    expect(Theme::fromString($name)->owner)->not->toBeEmpty();
})->with([
    'laravel/starter-kit',
    'baconfy/ui',
    'some-org/some.repo',
    'org123/repo_name',
]);

/**
 * The theme name goes into a URL. Anything that is not exactly owner/repo is
 * refused before it can steer the request somewhere else.
 */
it('refuses anything that is not owner/repo', function (string $name) {
    Theme::fromString($name);
})->throws(InvalidTheme::class)->with([
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
