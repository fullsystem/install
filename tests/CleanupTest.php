<?php

declare(strict_types=1);

use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\Filesystem;
use FullSystem\Install\Themes\FetchedTheme;
use Tests\Support\FakeThemeSource;

function temps(string $prefix): array
{
    return glob(sys_get_temp_dir().'/'.$prefix.'*') ?: [];
}

it('throws the download away when it is done with it', function () {
    $workspace = Filesystem::temporaryDirectory('fullsystem-discard-test-');
    mkdir($workspace.'/unpacked/theme-main', 0755, true);
    file_put_contents($workspace.'/theme.zip', 'zip');

    $theme = new FetchedTheme(new Schema('acme/theme', '1.0.0', []), $workspace.'/unpacked/theme-main', $workspace);

    $theme->discard();

    expect(is_dir($workspace))->toBeFalse();
});

it('does nothing when there is no workspace to throw away', function () {
    $theme = new FetchedTheme(new Schema('acme/theme', '1.0.0', []), tempDirectory());

    $theme->discard();
})->throwsNoExceptions();

it('leaves nothing behind after a run', function () {
    $before = temps('fullsystem-theme-');

    cli(['path' => laravelProject()], FakeThemeSource::returning());

    expect(temps('fullsystem-theme-'))->toBe($before);
});

describe('what earlier runs left behind', function () {
    /**
     * A run killed halfway never reaches its own cleanup, so the sweep is what
     * keeps the temp directory from collecting a copy of every theme ever
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
