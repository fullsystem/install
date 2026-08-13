<?php

declare(strict_types=1);

use FullSystem\Install\Context;
use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\Git;
use FullSystem\Install\Workspace;
use Tests\Support\FakeRecipeSource;

function log1(string $project): string
{
    exec('git -C '.escapeshellarg($project).' log -1 --pretty=%s', $out);

    return trim(implode('', $out));
}

it('starts the repository on main, whatever init.defaultBranch says', function () {
    $project = laravelProject();

    (new Workspace)->open(new Context(cwd: $project, recipe: 'acme/recipe'));

    exec('git -C '.escapeshellarg($project).' rev-parse --verify main', $out, $status);

    expect($status)->toBe(0);
});

it('names the first commit after the project', function () {
    $project = laravelProject();

    (new Workspace)->open(new Context(cwd: $project, recipe: 'acme/recipe'));

    expect(log1($project))->toBe('chore: start '.basename($project));
});

it('branches off after that commit, not before it', function () {
    $project = laravelProject();
    $workspace = new Workspace;

    $workspace->open(new Context(cwd: $project, recipe: 'acme/recipe'));

    expect((new Git)->currentBranch($project))->toBe(Workspace::WORK_BRANCH)
        ->and($workspace->origin())->toBe('main');
});

it('writes the install as a conventional commit', function () {
    $project = laravelProject();
    $recipe = tempDirectory();
    touchFile($recipe, 'source/resources/js/app.tsx', 'x');

    cli(['path' => $project], FakeRecipeSource::returning(
        new Schema('acme/recipe', '2.0.0', [], 'source'),
        $recipe,
    ));

    expect(log1($project))->toContain('feat: install acme/recipe 2.0.0');
});

it('leaves the project\'s own history alone when it already has one', function () {
    $project = laravelProject();
    exec('git -C '.escapeshellarg($project).' init -q');
    exec('git -C '.escapeshellarg($project).' -c user.email=t@t -c user.name=t add -A');
    exec('git -C '.escapeshellarg($project).' -c user.email=t@t -c user.name=t commit -q -m "their own message"');

    (new Workspace)->open(new Context(cwd: $project, recipe: 'acme/recipe'));

    expect(log1($project))->toBe('their own message');
});
