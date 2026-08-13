<?php

declare(strict_types=1);

use FullSystem\Install\Recipes\FetchedRecipe;
use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\Filesystem;
use Tests\Support\FakeRecipeSource;

function temps(string $prefix): array
{
    return glob(sys_get_temp_dir().'/'.$prefix.'*') ?: [];
}

it('throws the download away when it is done with it', function () {
    $workspace = Filesystem::temporaryDirectory('fullsystem-discard-test-');
    mkdir($workspace.'/unpacked/recipe-main', 0755, true);
    file_put_contents($workspace.'/recipe.zip', 'zip');

    $recipe = new FetchedRecipe(new Schema('acme/recipe', '1.0.0', []), $workspace.'/unpacked/recipe-main', $workspace);

    $recipe->discard();

    expect(is_dir($workspace))->toBeFalse();
});

it('does nothing when there is no workspace to throw away', function () {
    $recipe = new FetchedRecipe(new Schema('acme/recipe', '1.0.0', []), tempDirectory());

    $recipe->discard();
})->throwsNoExceptions();

it('leaves nothing behind after a run', function () {
    $before = temps('fullsystem-recipe-');

    cli(['path' => laravelProject()], FakeRecipeSource::returning());

    expect(temps('fullsystem-recipe-'))->toBe($before);
});

describe('what earlier runs left behind', function () {
    /**
     * A run killed halfway never reaches its own cleanup, so the sweep is what
     * keeps the temp directory from collecting a copy of every recipe ever
     * downloaded.
     */
    it('sweeps directories old enough that nothing could still be using them', function () {
        $stale = Filesystem::temporaryDirectory('fullsystem-sweep-test-');
        touch($stale, time() - 7200);

        Filesystem::forgetOlderThan('fullsystem-sweep-test-', 3600);

        expect(is_dir($stale))->toBeFalse();
    });

    it('leaves alone anything a running install might still need', function () {
        $fresh = Filesystem::temporaryDirectory('fullsystem-sweep-test-');

        Filesystem::forgetOlderThan('fullsystem-sweep-test-', 3600);

        expect(is_dir($fresh))->toBeTrue();

        Filesystem::delete($fresh);
    });

    it('touches nothing outside its own prefix', function () {
        $other = Filesystem::temporaryDirectory('someone-elses-');
        touch($other, time() - 7200);

        Filesystem::forgetOlderThan('fullsystem-sweep-test-', 3600);

        expect(is_dir($other))->toBeTrue();

        Filesystem::delete($other);
    });
});
