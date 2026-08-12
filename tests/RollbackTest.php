<?php

declare(strict_types=1);

use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\Git;
use FullSystem\Install\Workspace;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeProcess;
use Tests\Support\FakeThemeSource;

/**
 * A theme that deletes something and then runs a command — which is where a
 * failure hurts, because the deletion already happened.
 */
function destructiveTheme(): FakeThemeSource
{
    return FakeThemeSource::returning(new Schema(
        name: 'acme/theme',
        version: '1.0.0',
        phases: [
            'pre-install' => [
                ['remove' => ['resources/js/pages']],
                ['composer' => ['acme/package']],
            ],
        ],
    ));
}

it('starts a repository when the project has none', function () {
    $project = laravelProject();

    $tester = cli(['path' => $project], destructiveTheme());

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and((new Git)->isInsideWorkTree($project))->toBeTrue()
        ->and($tester->getDisplay())->toContain(Workspace::WORK_BRANCH);
});

it('puts back what an action deleted before a later one failed', function () {
    $project = laravelProject();
    touchFile($project, 'resources/js/pages/dashboard.tsx', 'the original');

    $processes = (new FakeProcess)->fails('composer require');

    $tester = cli(['path' => $project], destructiveTheme(), $processes);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('rolled back')
        ->and(file_get_contents($project.'/resources/js/pages/dashboard.tsx'))->toBe('the original');
});

it('leaves the deletion in place when everything succeeded', function () {
    $project = laravelProject();
    touchFile($project, 'resources/js/pages/dashboard.tsx', 'the original');

    $tester = cli(['path' => $project], destructiveTheme());

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and(file_exists($project.'/resources/js/pages/dashboard.tsx'))->toBeFalse();
});

it('commits the result on the work branch, leaving the tree clean', function () {
    $project = laravelProject();
    touchFile($project, 'resources/js/pages/dashboard.tsx', 'the original');

    cli(['path' => $project], destructiveTheme());

    expect((new Git)->dirtyFiles($project))->toBeEmpty();
});

it('touches no repository during a dry run', function () {
    $project = laravelProject();

    cli(['path' => $project, '--dry-run' => true], destructiveTheme());

    expect((new Git)->isInsideWorkTree($project))->toBeFalse();
});
