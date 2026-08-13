<?php

declare(strict_types=1);

use FullSystem\Install\Context;
use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\Git;
use FullSystem\Install\Workspace;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeRecipeSource;

function installableRecipe(): FakeRecipeSource
{
    $recipe = tempDirectory();
    touchFile($recipe, 'source/resources/js/app.tsx', 'the recipe app');

    return FakeRecipeSource::returning(
        new Schema('acme/recipe', '2.0.0', [], 'source'),
        $recipe,
    );
}

function branchOf(string $project): ?string
{
    return (new Git)->currentBranch($project);
}

it('does the work on its own branch', function () {
    $project = laravelProject();

    $display = cli(['path' => $project], installableRecipe())->getDisplay();

    expect($display)->toContain(Workspace::WORK_BRANCH);
});

describe('when the answer is yes', function () {
    it('applies the work to the branch the user started on', function () {
        $project = laravelProject();

        $tester = cli(['path' => $project], installableRecipe());

        expect($tester->getStatusCode())->toBe(Command::SUCCESS)
            ->and($tester->getDisplay())->toContain('Applied to')
            ->and(branchOf($project))->not->toBe(Workspace::WORK_BRANCH)
            ->and(file_get_contents($project.'/resources/js/app.tsx'))->toBe('the recipe app');
    });

    it('leaves the work branch behind once it is merged', function () {
        $project = laravelProject();

        cli(['path' => $project], installableRecipe());

        exec('git -C '.escapeshellarg($project).' rev-parse --verify '.Workspace::WORK_BRANCH.' 2>/dev/null', $out, $status);

        expect($status)->not->toBe(0);
    });

    it('keeps the restore point, so it is still undoable', function () {
        $project = laravelProject();

        cli(['path' => $project], installableRecipe());

        exec('git -C '.escapeshellarg($project).' rev-parse --verify '.Workspace::RESTORE_POINT, $out, $status);

        expect($status)->toBe(0);
    });
});

/**
 * ApplicationTester runs non-interactively, where confirm() resolves to its
 * default — which is yes. Saying no is the interactive path, and what it must
 * do is keep the work rather than throw it away.
 */
describe('when the answer is no', function () {
    it('keeps the branch and says how to apply it later', function () {
        $project = laravelProject();
        $workspace = new Workspace;
        $context = new Context(cwd: $project, recipe: 'acme/recipe');

        $workspace->open($context);
        touchFile($project, 'resources/js/app.tsx', 'the recipe app');
        $workspace->keep($context, 'Install acme/recipe');

        $workspace->leave($context);

        expect(branchOf($project))->not->toBe(Workspace::WORK_BRANCH)
            ->and(file_exists($project.'/resources/js/app.tsx'))->toBeFalse()
            ->and($workspace->applyCommand())->toContain('git merge '.Workspace::WORK_BRANCH);
    });

    it('still has the work on the branch', function () {
        $project = laravelProject();
        $workspace = new Workspace;
        $context = new Context(cwd: $project, recipe: 'acme/recipe');

        $workspace->open($context);
        touchFile($project, 'resources/js/app.tsx', 'the recipe app');
        $workspace->keep($context, 'Install acme/recipe');
        $workspace->leave($context);

        exec('git -C '.escapeshellarg($project).' checkout -q '.Workspace::WORK_BRANCH);

        expect(file_get_contents($project.'/resources/js/app.tsx'))->toBe('the recipe app');
    });
});
