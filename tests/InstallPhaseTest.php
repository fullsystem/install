<?php

declare(strict_types=1);

use FullSystem\Install\Schema\Schema;
use Symfony\Component\Console\Command\Command;
use Tests\Support\FakeProcess;
use Tests\Support\FakeThemeSource;

/**
 * A theme directory with files under $source, mirroring the project root.
 */
function themeShipping(array $files, string $source = 'stubs'): string
{
    $path = tempDirectory();

    foreach ($files as $file => $contents) {
        touchFile($path, $source.'/'.$file, $contents);
    }

    return $path;
}

function themeWith(array $files, array $phases = [], string $source = 'stubs'): FakeThemeSource
{
    return FakeThemeSource::returning(
        new Schema('acme/theme', '1.0.0', $phases, $source),
        themeShipping($files, $source),
    );
}

it('copies the theme files onto the project', function () {
    $project = laravelProject();

    $source = themeWith([
        'resources/js/app.tsx' => 'the theme app',
        'routes/web.php' => '<?php // the theme routes',
    ]);

    $tester = cli(['path' => $project], $source);

    expect($tester->getStatusCode())->toBe(Command::SUCCESS)
        ->and(file_get_contents($project.'/resources/js/app.tsx'))->toBe('the theme app')
        ->and(file_get_contents($project.'/routes/web.php'))->toBe('<?php // the theme routes');
});

it('overwrites what the starter kit had there', function () {
    $project = laravelProject();
    touchFile($project, 'resources/js/app.tsx', 'the starter kit app');

    cli(['path' => $project], themeWith(['resources/js/app.tsx' => 'the theme app']));

    expect(file_get_contents($project.'/resources/js/app.tsx'))->toBe('the theme app');
});

it('creates directories the project does not have yet', function () {
    $project = laravelProject();

    cli(['path' => $project], themeWith(['resources/js/layouts/deep/nested.tsx' => 'x']));

    expect(file_exists($project.'/resources/js/layouts/deep/nested.tsx'))->toBeTrue();
});

it('reads the source directory the theme declared', function () {
    $project = laravelProject();

    cli(['path' => $project], themeWith(['routes/web.php' => 'from files/'], source: 'files'));

    expect(file_get_contents($project.'/routes/web.php'))->toBe('from files/');
});

it('fails when the theme does not ship what it declared', function () {
    $project = laravelProject();
    $empty = FakeThemeSource::returning(new Schema('acme/theme', '1.0.0', []), tempDirectory());

    $tester = cli(['path' => $project], $empty);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('does not ship it');
});

it('copies nothing during a dry run', function () {
    $project = laravelProject();

    $tester = cli(['path' => $project, '--dry-run' => true], themeWith(['resources/js/app.tsx' => 'x']));

    expect(file_exists($project.'/resources/js/app.tsx'))->toBeFalse()
        ->and($tester->getDisplay())->toContain('would copy');
});

describe('order', function () {
    it('copies after pre-install and before post-install', function () {
        $project = laravelProject();
        $processes = new FakeProcess;

        $source = themeWith(
            ['resources/js/app.tsx' => 'x'],
            [
                'pre-install' => [['remove' => ['resources/js/app.tsx']]],
                'post-install' => [['artisan' => ['wayfinder:generate']]],
            ],
        );

        cli(['path' => $project], $source, $processes);

        // pre-install deleted it, install put the theme's version there, and
        // post-install ran afterwards — so the file survives.
        expect(file_get_contents($project.'/resources/js/app.tsx'))->toBe('x')
            ->and($processes->lines())->toContain(PHP_BINARY.' artisan wayfinder:generate');
    });
});

describe('verification', function () {
    it('runs after everything else, quietly', function () {
        $processes = new FakeProcess;

        $tester = cli(['path' => laravelProject()], themeWith(['routes/web.php' => 'x']), $processes);

        expect($processes->lines())->toBe(['npm run build', 'composer test'])
            ->and($tester->getDisplay())->toContain('2 check(s) passed');
    });

    it('shows the output only when it fails', function () {
        $processes = (new FakeProcess)
            ->fails('npm run build')
            ->outputs("error TS2307: Cannot find module '@/routes'");

        $tester = cli(['path' => laravelProject()], themeWith(['routes/web.php' => 'x']), $processes);

        expect($tester->getStatusCode())->toBe(Command::FAILURE)
            ->and($tester->getDisplay())->toContain('Cannot find module');
    });

    it('rolls the project back when verification fails', function () {
        $project = laravelProject();
        touchFile($project, 'routes/web.php', 'the original routes');

        $processes = (new FakeProcess)->fails('composer test');

        $tester = cli(['path' => $project], themeWith(['routes/web.php' => 'the theme routes']), $processes);

        expect($tester->getStatusCode())->toBe(Command::FAILURE)
            ->and($tester->getDisplay())->toContain('rolled back')
            ->and(file_get_contents($project.'/routes/web.php'))->toBe('the original routes');
    });

    it('does not run during a dry run', function () {
        $processes = new FakeProcess;

        $tester = cli(['path' => laravelProject(), '--dry-run' => true], themeWith(['routes/web.php' => 'x']), $processes);

        expect($processes->calls)->toBeEmpty()
            ->and($tester->getDisplay())->toContain('would run: npm run build, composer test');
    });
});
