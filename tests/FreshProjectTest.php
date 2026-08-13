<?php

declare(strict_types=1);

use FullSystem\Install\Checks\FreshProject;
use FullSystem\Install\Context;
use FullSystem\Install\Result;

/**
 * The tree `laravel new --react` leaves behind, as far as this check reads it.
 */
function freshProject(): string
{
    $path = laravelProject();

    mkdir($path.'/resources/js/pages', 0755, true);
    file_put_contents($path.'/resources/js/pages/dashboard.tsx', '');

    mkdir($path.'/app/Models', 0755, true);
    file_put_contents($path.'/app/Models/User.php', '<?php');

    return $path;
}

function freshness(string $path): Result
{
    return (new FreshProject)->run(new Context(cwd: $path, recipe: 'acme/recipe'));
}

it('passes on a project straight out of laravel new', function () {
    expect(freshness(freshProject())->ok)->toBeTrue();
});

it('can be forced past, because it is a heuristic', function () {
    expect((new FreshProject)->forceable())->toBeTrue();
});

/**
 * Real git, not a fake: what the check reads is git's own answer, and a fake
 * would only prove that the fake works.
 */
function gitProject(int $commits): string
{
    $path = freshProject();
    $git = 'git -C '.escapeshellarg($path).' -c user.email=t@t -c user.name=t';

    exec("{$git} init -q");

    for ($i = 1; $i <= $commits; $i++) {
        file_put_contents($path."/commit-{$i}.txt", (string) $i);
        exec("{$git} add -A && {$git} commit -q -m 'commit {$i}'");
    }

    return $path;
}

describe('signals that someone already built here', function () {
    it('notices a history longer than the initial commit', function () {
        $result = freshness(gitProject(3));

        expect($result->ok)->toBeFalse()
            ->and($result->reason)->toContain('3 commits');
    });

    it('accepts the single commit laravel new leaves', function () {
        expect(freshness(gitProject(1))->ok)->toBeTrue();
    });

    it('says nothing about a directory that is not a repository', function () {
        expect(freshness(freshProject())->ok)->toBeTrue();
    });

    it('notices the starter kit pages are gone', function () {
        $path = freshProject();
        unlink($path.'/resources/js/pages/dashboard.tsx');

        $result = freshness($path);

        expect($result->ok)->toBeFalse()
            ->and($result->reason)->toContain('pages');
    });

    it('notices models beyond the one the kit ships', function () {
        $path = freshProject();
        file_put_contents($path.'/app/Models/Post.php', '<?php');
        file_put_contents($path.'/app/Models/Comment.php', '<?php');

        $result = freshness($path);

        expect($result->ok)->toBeFalse()
            ->and($result->reason)->toContain('2 model');
    });

    it('reports every signal it found, not just the first', function () {
        $path = freshProject();
        unlink($path.'/resources/js/pages/dashboard.tsx');
        file_put_contents($path.'/app/Models/Post.php', '<?php');

        $reason = (string) freshness($path)->reason;

        expect($reason)->toContain('pages')
            ->and($reason)->toContain('model');
    });
});

describe('what it must not mistake for work', function () {
    /**
     * The React kit ships passkeys and two-factor migrations on top of the
     * skeleton's three, and which ones exist depends on the flags given to
     * `laravel new`. Counting migrations would fail a project nobody touched.
     */
    it('ignores migrations entirely', function () {
        $path = freshProject();
        mkdir($path.'/database/migrations', 0755, true);

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '0001_01_01_000001_create_cache_table.php',
            '0001_01_01_000002_create_jobs_table.php',
            '2024_01_01_000000_create_passkeys_table.php',
            '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        ] as $migration) {
            file_put_contents($path.'/database/migrations/'.$migration, '<?php');
        }

        expect(freshness($path)->ok)->toBeTrue();
    });

    it('does not trip on a project without an app/Models directory', function () {
        $path = freshProject();
        unlink($path.'/app/Models/User.php');
        rmdir($path.'/app/Models');

        expect(freshness($path)->ok)->toBeTrue();
    });
});
