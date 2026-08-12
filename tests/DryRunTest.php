<?php

declare(strict_types=1);

use FullSystem\Install\Schema\Schema;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeThemeSource;

function themeWithActions(): FakeThemeSource
{
    return FakeThemeSource::returning(new Schema('acme/theme', '1.0.0', [
        'pre-install' => [
            ['composer' => ['laravel/reverb', 'laravel/horizon']],
            ['composer' => ['pestphp/pest'], 'dev' => true],
            ['remove' => ['routes/web.php', 'resources/js/pages']],
        ],
        'post-install' => [
            ['artisan' => ['wayfinder:generate --with-form']],
        ],
    ]));
}

it('prints the plan and writes nothing', function () {
    $tester = cli(['path' => laravelProject(), '--dry-run' => true], themeWithActions());

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('Nothing was written');
});

it('lists every action in the order the theme declared them', function () {
    $display = cli(['path' => laravelProject(), '--dry-run' => true], themeWithActions())->getDisplay();

    $positions = [];

    foreach (['pre-install', 'composer', 'remove', 'post-install', 'artisan'] as $needle) {
        $at = strpos($display, $needle);

        expect($at)->not->toBeFalse("missing from the plan: {$needle}");

        $positions[$needle] = $at;
    }

    $ascending = array_values($positions);
    sort($ascending);

    expect(array_values($positions))->toBe($ascending);
});

it('shows the modifiers of an action', function () {
    expect(cli(['path' => laravelProject(), '--dry-run' => true], themeWithActions())->getDisplay())
        ->toContain('(dev)');
});

/**
 * The plan is not a --dry-run feature: a command that deletes files should
 * never do it silently.
 */
it('prints the plan on a normal run too', function () {
    expect(cli(['path' => laravelProject()], themeWithActions())->getDisplay())
        ->toContain('pre-install')
        ->toContain('artisan');
});

it('refuses an action the driver does not know', function () {
    $source = FakeThemeSource::returning(new Schema('acme/theme', '1.0.0', [
        'pre-install' => [['docker' => ['up']]],
    ]));

    $tester = cli(['path' => laravelProject()], $source);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('docker');
});
