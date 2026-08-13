<?php

declare(strict_types=1);

use FullSystem\Install\Schema\Schema;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeRecipeSource;

function recipeRequiring(array $requires): FakeRecipeSource
{
    return FakeRecipeSource::returning(new Schema(
        name: 'acme/recipe',
        version: '1.0.0',
        phases: [],
        requires: $requires,
    ));
}

it('reads what the recipe requires', function () {
    $schema = Schema::fromJson('{"requires": ["fresh-project"]}');

    expect($schema->requires)->toBe(['fresh-project']);
});

it('requires nothing by default', function () {
    expect(Schema::fromJson('{}')->requires)->toBe([]);
});

it('ignores requires of the wrong shape', function () {
    expect(Schema::fromJson('{"requires": "fresh-project"}')->requires)->toBe([])
        ->and(Schema::fromJson('{"requires": [42, "fresh-project"]}')->requires)->toBe(['fresh-project']);
});

it('runs a check the recipe requires', function () {
    // A project with the starter kit pages still in place is fresh enough.
    $project = laravelProject();
    mkdir($project.'/resources/js/pages', 0755, true);
    file_put_contents($project.'/resources/js/pages/dashboard.tsx', '');

    $tester = cli(['path' => $project], recipeRequiring(['fresh-project']));

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('fresh-project');
});

it('stops when a required check fails', function () {
    // No starter kit pages: something already replaced them.
    $tester = cli(['path' => laravelProject()], recipeRequiring(['fresh-project']));

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('fresh-project');
});

it('does not run a check the recipe did not require', function () {
    $tester = cli(['path' => laravelProject()], recipeRequiring([]));

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->not->toContain('fresh-project');
});

it('continues past a required check with --force', function () {
    $tester = cli(['path' => laravelProject(), '--force' => true], recipeRequiring(['fresh-project']));

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('--force');
});

it('refuses a check the driver does not offer', function () {
    $tester = cli(['path' => laravelProject()], recipeRequiring(['sunshine']));

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('sunshine');
});
