<?php

declare(strict_types=1);

use FullSystem\Install\Actions\Action;
use FullSystem\Install\Actions\Artisan;
use FullSystem\Install\Actions\Composer;
use FullSystem\Install\Actions\Packages;
use FullSystem\Install\Actions\Remove;
use FullSystem\Install\Actions\Shadcn;
use FullSystem\Install\Context;
use Tests\Support\FakeProcess;

function act(string $name, mixed $parameters, array $modifiers = []): Action
{
    return new Action($name, $parameters, $modifiers);
}

function ctx(?string $cwd = null, bool $dryRun = false): Context
{
    return new Context(cwd: $cwd ?? tempDirectory(), theme: 'acme/theme', dryRun: $dryRun);
}

describe('composer', function () {
    it('requires the packages', function () {
        $processes = new FakeProcess;

        (new Composer($processes))->run(ctx(), act('composer', ['laravel/reverb', 'laravel/horizon']));

        expect($processes->lines())->toBe(['composer require laravel/reverb laravel/horizon']);
    });

    it('passes --dev when the modifier says so', function () {
        $processes = new FakeProcess;

        (new Composer($processes))->run(ctx(), act('composer', ['pestphp/pest'], ['dev' => true]));

        expect($processes->lines())->toBe(['composer require --dev pestphp/pest']);
    });

    it('accepts a version constraint', function () {
        $processes = new FakeProcess;

        (new Composer($processes))->run(ctx(), act('composer', ['laravel/reverb:^1.0']));

        expect($processes->lines())->toBe(['composer require laravel/reverb:^1.0']);
    });

    /**
     * There is no shell to inject into, but composer reads its own arguments:
     * a "package" named --ignore-platform-reqs would be obeyed as a flag.
     */
    it('refuses a name that composer would read as a flag', function (string $name) {
        $processes = new FakeProcess;

        $result = (new Composer($processes))->run(ctx(), act('composer', [$name]));

        expect($result->ok)->toBeFalse()
            ->and($processes->calls)->toBeEmpty();
    })->with(['--ignore-platform-reqs', '-n', 'laravel/reverb; rm -rf /', 'not a package']);

    it('runs nothing during a dry run', function () {
        $processes = new FakeProcess;

        $result = (new Composer($processes))->run(ctx(dryRun: true), act('composer', ['laravel/reverb']));

        expect($processes->calls)->toBeEmpty()
            ->and($result->message)->toContain('would run: composer require laravel/reverb');
    });

    it('fails when composer fails', function () {
        $processes = (new FakeProcess)->fails('composer require');

        expect((new Composer($processes))->run(ctx(), act('composer', ['laravel/reverb']))->ok)->toBeFalse();
    });
});

describe('packages', function () {
    it('uses the manager the project already has', function (string $lockfile, string $expected) {
        $cwd = tempDirectory();
        touch($cwd.'/'.$lockfile);
        $processes = new FakeProcess;

        (new Packages($processes))->run(ctx($cwd), act('packages', ['date-fns']));

        expect($processes->lines()[0])->toStartWith($expected);
    })->with([
        ['pnpm-lock.yaml', 'pnpm add'],
        ['yarn.lock', 'yarn add'],
        ['bun.lockb', 'bun add'],
        ['package-lock.json', 'npm install'],
    ]);

    it('falls back to npm when there is no lockfile', function () {
        $processes = new FakeProcess;

        (new Packages($processes))->run(ctx(), act('packages', ['date-fns']));

        expect($processes->lines())->toBe(['npm install date-fns']);
    });

    it('uses the dev flag of that manager', function () {
        $cwd = tempDirectory();
        touch($cwd.'/pnpm-lock.yaml');
        $processes = new FakeProcess;

        (new Packages($processes))->run(ctx($cwd), act('packages', ['vite'], ['dev' => true]));

        expect($processes->lines())->toBe(['pnpm add -D vite']);
    });

    it('accepts a scoped package', function () {
        $processes = new FakeProcess;

        (new Packages($processes))->run(ctx(), act('packages', ['@laravel/echo-react']));

        expect($processes->lines())->toBe(['npm install @laravel/echo-react']);
    });

    it('refuses a name with a shell metacharacter', function () {
        $processes = new FakeProcess;

        $result = (new Packages($processes))->run(ctx(), act('packages', ['pusher-js; rm -rf /']));

        expect($result->ok)->toBeFalse()
            ->and($processes->calls)->toBeEmpty();
    });
});

describe('remove', function () {
    it('deletes files and directories', function () {
        $cwd = tempDirectory();
        touchFile($cwd, 'routes/web.php');
        touchFile($cwd, 'resources/js/pages/dashboard.tsx');

        (new Remove)->run(ctx($cwd), act('remove', ['routes/web.php', 'resources/js/pages']));

        expect(file_exists($cwd.'/routes/web.php'))->toBeFalse()
            ->and(is_dir($cwd.'/resources/js/pages'))->toBeFalse();
    });

    it('skips what is not there', function () {
        $cwd = tempDirectory();

        expect((new Remove)->run(ctx($cwd), act('remove', ['routes/web.php']))->ok)->toBeTrue();
    });

    /**
     * The whole list is checked first: a bad path at position two must not
     * leave the project with position one already deleted.
     */
    it('deletes nothing when any path escapes the project', function (string $path) {
        $cwd = tempDirectory();
        touchFile($cwd, 'routes/web.php');

        $result = (new Remove)->run(ctx($cwd), act('remove', ['routes/web.php', $path]));

        expect($result->ok)->toBeFalse()
            ->and(file_exists($cwd.'/routes/web.php'))->toBeTrue();
    })->with(['../outside', '/etc/passwd', '..', 'resources/../../escape']);

    it('deletes nothing during a dry run', function () {
        $cwd = tempDirectory();
        touchFile($cwd, 'routes/web.php');

        $result = (new Remove)->run(ctx($cwd, dryRun: true), act('remove', ['routes/web.php']));

        expect(file_exists($cwd.'/routes/web.php'))->toBeTrue()
            ->and($result->message)->toContain('would remove');
    });
});

describe('artisan', function () {
    it('runs each command with the php that is running', function () {
        $processes = new FakeProcess;

        (new Artisan($processes))->run(ctx(), act('artisan', ['storage:link', 'wayfinder:generate --with-form']));

        expect($processes->lines())->toBe([
            PHP_BINARY.' artisan storage:link',
            PHP_BINARY.' artisan wayfinder:generate --with-form',
        ]);
    });

    it('allows the install commands a theme legitimately needs', function () {
        $processes = new FakeProcess;

        $result = (new Artisan($processes))->run(ctx(), act('artisan', ['reverb:install']));

        expect($result->ok)->toBeTrue();
    });

    it('refuses the commands that destroy data', function (string $command) {
        $processes = new FakeProcess;

        $result = (new Artisan($processes))->run(ctx(), act('artisan', [$command]));

        expect($result->ok)->toBeFalse()
            ->and($result->reason)->toContain('destroys data')
            ->and($processes->calls)->toBeEmpty();
    })->with(['db:wipe', 'migrate:fresh', 'migrate:reset', 'migrate:rollback']);

    it('refuses a command carrying a shell operator', function (string $entry) {
        $processes = new FakeProcess;

        expect((new Artisan($processes))->run(ctx(), act('artisan', [$entry]))->ok)->toBeFalse()
            ->and($processes->calls)->toBeEmpty();
    })->with([
        'migrate; rm -rf /',
        'migrate && curl evil.test',
        'migrate --force=$(whoami)',
        '$(id)',
    ]);

    it('stops at the first command that fails', function () {
        $processes = (new FakeProcess)->fails('storage:link');

        $result = (new Artisan($processes))->run(ctx(), act('artisan', ['storage:link', 'optimize:clear']));

        expect($result->ok)->toBeFalse()
            ->and($processes->lines())->toHaveCount(1);
    });
});

describe('shadcn', function () {
    it('initialises with the declared preset and then adds', function () {
        $processes = new FakeProcess;

        (new Shadcn($processes))->run(ctx(), act('shadcn', [
            'preset' => 'vega', 'template' => 'laravel', 'pointer' => true, 'components' => 'all',
        ]));

        expect($processes->lines())->toBe([
            'npx --yes shadcn@latest init -p vega -t laravel --pointer -f -y --reinstall',
            'npx --yes shadcn@latest add --all --overwrite --yes',
        ]);
    });

    it('adds only the declared components', function () {
        $processes = new FakeProcess;

        (new Shadcn($processes))->run(ctx(), act('shadcn', ['components' => ['button', 'card']]));

        expect($processes->lines()[1])->toBe('npx --yes shadcn@latest add button card --overwrite --yes');
    });

    it('passes --no-pointer only when pointer is explicitly false', function () {
        $processes = new FakeProcess;

        (new Shadcn($processes))->run(ctx(), act('shadcn', ['pointer' => false]));

        expect($processes->lines()[0])->toContain('--no-pointer');
    });

    it('refuses an option that is not a plain name', function (string $key, string $value) {
        $processes = new FakeProcess;

        expect((new Shadcn($processes))->run(ctx(), act('shadcn', [$key => $value]))->ok)->toBeFalse()
            ->and($processes->calls)->toBeEmpty();
    })->with([
        ['preset', '--force'],
        ['base', 'radix; ls'],
        ['template', '../escape'],
    ]);
});
