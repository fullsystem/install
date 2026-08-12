<?php

declare(strict_types=1);

use FullSystem\Install\Checks\CleanWorktree;
use FullSystem\Install\Context;
use FullSystem\Install\RestorePoint;
use FullSystem\Install\Support\Git;

function project(bool $gitignore = true): string
{
    $path = tempDirectory();

    touchFile($path, 'routes/web.php', 'original');

    if ($gitignore) {
        file_put_contents($path.'/.gitignore', "/vendor\n/node_modules\n.env\n");
    }

    return $path;
}

function repository(string $path): string
{
    exec('git -C '.escapeshellarg($path).' init -q');
    exec('git -C '.escapeshellarg($path).' -c user.email=t@t -c user.name=t add -A');
    exec('git -C '.escapeshellarg($path).' -c user.email=t@t -c user.name=t commit -q -m first');

    return $path;
}

function context(string $path): Context
{
    return new Context(cwd: $path, theme: 'acme/theme');
}

describe('a project without git', function () {
    it('starts a repository and commits, so there is something to go back to', function () {
        $path = project();

        $result = (new RestorePoint)->establish(context($path));

        expect($result->ok)->toBeTrue()
            ->and((new Git)->isInsideWorkTree($path))->toBeTrue()
            ->and((new Git)->commitCount($path))->toBe(1);
    });

    /**
     * `git add -A` without a .gitignore commits .env and node_modules. It
     * never leaves the machine, but credentials in the history are not ours
     * to put there.
     */
    it('refuses to commit a project with no .gitignore', function () {
        $path = project(gitignore: false);

        $result = (new RestorePoint)->establish(context($path));

        expect($result->ok)->toBeFalse()
            ->and($result->reason)->toContain('.gitignore')
            ->and((new Git)->isInsideWorkTree($path))->toBeFalse();
    });
});

describe('a project with git', function () {
    it('keeps the repository it found', function () {
        $path = repository(project());
        $before = (new Git)->head($path);

        (new RestorePoint)->establish(context($path));

        expect((new Git)->head($path))->toBe($before)
            ->and((new Git)->commitCount($path))->toBe(1);
    });

    it('marks the point by name, so it survives a run that dies halfway', function () {
        $path = repository(project());

        (new RestorePoint)->establish(context($path));

        exec('git -C '.escapeshellarg($path).' rev-parse '.escapeshellarg(RestorePoint::NAME), $output, $status);

        expect($status)->toBe(0);
    });

    it('can be established twice without complaining', function () {
        $path = repository(project());
        $point = new RestorePoint;

        $point->establish(context($path));

        expect($point->establish(context($path))->ok)->toBeTrue();
    });
});

describe('going back', function () {
    it('restores files the run changed and removes files it added', function () {
        $path = repository(project());
        $point = new RestorePoint;
        $point->establish(context($path));

        file_put_contents($path.'/routes/web.php', 'wrecked');
        touchFile($path, 'resources/js/new-file.tsx', 'added');
        unlink($path.'/.gitignore');

        expect($point->restore(context($path)))->toBeTrue()
            ->and(file_get_contents($path.'/routes/web.php'))->toBe('original')
            ->and(file_exists($path.'/resources/js/new-file.tsx'))->toBeFalse()
            ->and(file_exists($path.'/.gitignore'))->toBeTrue();
    });

    it('tells you how to do it by hand', function () {
        expect((new RestorePoint)->undoCommand())
            ->toContain(RestorePoint::NAME)
            ->toContain('git reset --hard');
    });
});

describe('clean-worktree', function () {
    it('passes on a committed project', function () {
        expect((new CleanWorktree)->run(context(repository(project())))->ok)->toBeTrue();
    });

    it('passes when there is no repository yet, because one is about to be made', function () {
        expect((new CleanWorktree)->run(context(project()))->ok)->toBeTrue();
    });

    it('fails when a rollback would discard uncommitted work', function () {
        $path = repository(project());
        file_put_contents($path.'/routes/web.php', 'work in progress');

        $result = (new CleanWorktree)->run(context($path));

        expect($result->ok)->toBeFalse()
            ->and($result->reason)->toContain('1 uncommitted');
    });

    it('can be forced past', function () {
        expect((new CleanWorktree)->forceable())->toBeTrue();
    });
});
