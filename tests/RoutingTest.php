<?php

declare(strict_types=1);

use FullSystem\Install\Application;

/**
 * The first token is ambiguous: `cpx fullsystem/install .` means "install
 * here" and `cpx fullsystem/install login` means "run the login command".
 * Symfony resolves the first argument as a command name and nothing else, so
 * the decision has to be made before it gets the input.
 */
function route(array $tokens): array
{
    return (new Application)->route($tokens);
}

it('runs the installer when there is nothing to route', function () {
    expect(route([]))->toBe(['install']);
});

it('treats a known command as a command', function (string $command) {
    expect(route([$command]))->toBe([$command]);
})->with(['help', 'list', 'install']);

it('treats an alias as a command', function () {
    expect(route(['init']))->toBe(['init']);
});

it('keeps the arguments that follow a command', function () {
    expect(route(['help', 'install']))->toBe(['help', 'install']);
});

it('treats anything else as a path for the installer', function (string $token) {
    expect(route([$token]))->toBe(['install', $token]);
})->with(['.', '..', './some/project', '/tmp/project', 'my-app']);

it('sends options to the installer', function () {
    expect(route(['--recipe=acme/recipe']))->toBe(['install', '--recipe=acme/recipe'])
        ->and(route(['-t', 'acme/recipe']))->toBe(['install', '-t', 'acme/recipe'])
        ->and(route(['--help']))->toBe(['install', '--help']);
});

it('sends a path with its options to the installer', function () {
    expect(route(['.', '--recipe=acme/recipe']))->toBe(['install', '.', '--recipe=acme/recipe']);
});

it('leaves an explicit install invocation alone', function () {
    expect(route(['install', '.']))->toBe(['install', '.']);
});

/**
 * A directory named like a command is unreachable as a bare token — the
 * command wins. `./login` is how you say you meant the directory.
 */
it('resolves the ambiguity in favour of the command', function () {
    expect(route(['list']))->toBe(['list'])
        ->and(route(['./list']))->toBe(['install', './list']);
});
