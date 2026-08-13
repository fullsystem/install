<?php

declare(strict_types=1);

use FullSystem\Install\Schema\Schema;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeProcess;
use Tests\Support\FakeRecipeSource;

/**
 * A recipe directory with files under $source, mirroring the project root.
 */
function recipeShipping(array $files, string $source = 'stubs'): string
{
    $path = tempDirectory();

    foreach ($files as $file => $contents) {
        touchFile($path, $source.'/'.$file, $contents);
    }

    return $path;
}

function recipeWith(array $files, array $phases = [], string $source = 'stubs'): FakeRecipeSource
{
    return FakeRecipeSource::returning(
        new Schema('acme/recipe', '1.0.0', $phases, $source),
        recipeShipping($files, $source),
    );
}

it('copies the recipe files onto the project', function () {
    $project = laravelProject();

    $source = recipeWith([
        'resources/js/app.tsx' => 'the recipe app',
        'routes/web.php' => '<?php // the recipe routes',
    ]);

    $tester = cli(['path' => $project], $source);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and(file_get_contents($project.'/resources/js/app.tsx'))->toBe('the recipe app')
        ->and(file_get_contents($project.'/routes/web.php'))->toBe('<?php // the recipe routes');
});

it('overwrites what the starter kit had there', function () {
    $project = laravelProject();
    touchFile($project, 'resources/js/app.tsx', 'the starter kit app');

    cli(['path' => $project], recipeWith(['resources/js/app.tsx' => 'the recipe app']));

    expect(file_get_contents($project.'/resources/js/app.tsx'))->toBe('the recipe app');
});

it('creates directories the project does not have yet', function () {
    $project = laravelProject();

    cli(['path' => $project], recipeWith(['resources/js/layouts/deep/nested.tsx' => 'x']));

    expect(file_exists($project.'/resources/js/layouts/deep/nested.tsx'))->toBeTrue();
});

it('reads the source directory the recipe declared', function () {
    $project = laravelProject();

    cli(['path' => $project], recipeWith(['routes/web.php' => 'from files/'], source: 'files'));

    expect(file_get_contents($project.'/routes/web.php'))->toBe('from files/');
});

it('fails when the recipe does not ship what it declared', function () {
    $project = laravelProject();
    $empty = FakeRecipeSource::returning(new Schema('acme/recipe', '1.0.0', []), tempDirectory());

    $tester = cli(['path' => $project], $empty);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('does not ship it');
});

it('copies nothing during a dry run', function () {
    $project = laravelProject();

    $tester = cli(['path' => $project, '--dry-run' => true], recipeWith(['resources/js/app.tsx' => 'x']));

    expect(file_exists($project.'/resources/js/app.tsx'))->toBeFalse()
        ->and($tester->getDisplay())->toContain('would copy');
});

describe('order', function () {
    it('copies after pre-install and before post-install', function () {
        $project = laravelProject();
        $processes = new FakeProcess;

        $source = recipeWith(
            ['resources/js/app.tsx' => 'x'],
            [
                'pre-install' => [['remove' => ['resources/js/app.tsx']]],
                'post-install' => [['artisan' => ['wayfinder:generate']]],
            ],
        );

        cli(['path' => $project], $source, $processes);

        // pre-install deleted it, install put the recipe's version there, and
        // post-install ran afterwards — so the file survives.
        expect(file_get_contents($project.'/resources/js/app.tsx'))->toBe('x')
            ->and($processes->lines())->toContain(PHP_BINARY.' artisan wayfinder:generate');
    });
});

describe('verification', function () {
    it('runs after everything else, quietly', function () {
        $processes = new FakeProcess;

        $tester = cli(['path' => laravelProject()], recipeWith(['routes/web.php' => 'x']), $processes);

        expect($processes->lines())->toBe(['composer lint', 'npm run build', 'composer test'])
            ->and($tester->getDisplay())->toContain('3 check(s) passed');
    });

    /**
     * The starter kit's own `composer test` runs lint:check before the suite,
     * so a recipe whose files arrive in a different style would fail an install
     * that worked. Fixing before checking is the only order that survives it.
     */
    it('lints before it tests', function () {
        $processes = new FakeProcess;

        cli(['path' => laravelProject()], recipeWith(['routes/web.php' => 'x']), $processes);

        $lines = $processes->lines();

        expect(array_search('composer lint', $lines, true))
            ->toBeLessThan((int) array_search('composer test', $lines, true));
    });

    it('skips a step the project does not declare', function () {
        $project = laravelProject();
        // The plain skeleton: a test script, and no lint.
        file_put_contents($project.'/composer.json', '{"scripts":{"test":["@php artisan test"]}}');

        $processes = new FakeProcess;

        cli(['path' => $project], recipeWith(['routes/web.php' => 'x']), $processes);

        expect($processes->lines())->not->toContain('composer lint')
            ->and($processes->lines())->toContain('composer test');
    });

    it('runs nothing when the project declares neither', function () {
        $project = laravelProject();
        file_put_contents($project.'/composer.json', '{}');
        file_put_contents($project.'/package.json', '{"dependencies":{"@inertiajs/react":"^3.6.1"}}');

        $processes = new FakeProcess;

        $tester = cli(['path' => $project], recipeWith(['routes/web.php' => 'x']), $processes);

        expect($tester->getStatusCode())->toBe(0)
            ->and($processes->calls)->toBeEmpty();
    });

    it('shows the output only when it fails', function () {
        $processes = (new FakeProcess)
            ->fails('npm run build')
            ->outputs("error TS2307: Cannot find module '@/routes'");

        $tester = cli(['path' => laravelProject()], recipeWith(['routes/web.php' => 'x']), $processes);

        expect($tester->getStatusCode())->toBe(Command::FAILURE)
            ->and($tester->getDisplay())->toContain('Cannot find module');
    });

    it('rolls the project back when verification fails', function () {
        $project = laravelProject();
        touchFile($project, 'routes/web.php', 'the original routes');

        $processes = (new FakeProcess)->fails('composer test');

        $tester = cli(['path' => $project], recipeWith(['routes/web.php' => 'the recipe routes']), $processes);

        expect($tester->getStatusCode())->toBe(Command::FAILURE)
            ->and($tester->getDisplay())->toContain('rolled back')
            ->and(file_get_contents($project.'/routes/web.php'))->toBe('the original routes');
    });

    it('does not run during a dry run', function () {
        $processes = new FakeProcess;

        $tester = cli(['path' => laravelProject(), '--dry-run' => true], recipeWith(['routes/web.php' => 'x']), $processes);

        expect($processes->calls)->toBeEmpty()
            ->and($tester->getDisplay())->toContain('would run: composer lint, npm run build, composer test');
    });
});
