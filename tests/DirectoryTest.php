<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;

it('creates the directory when its parent exists', function () {
    $parent = tempDirectory();
    $target = $parent.'/app';

    $tester = cli(['path' => $target]);

    expect(is_dir($target))->toBeTrue()
        ->and($tester->getDisplay())->toContain('created');
});

/**
 * One level, not the whole path: `install ./a/b/c` with no `a` is a typo far
 * more often than an intention, and mkdir -p would hide it.
 */
it('does not build a path that does not exist', function () {
    $parent = tempDirectory();

    $tester = cli(['path' => $parent.'/nope/deeper/app']);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and(is_dir($parent.'/nope'))->toBeFalse()
        ->and($tester->getDisplay())->toContain('Cannot create');
});

it('still refuses a path that is a file', function () {
    $file = (string) tempnam(sys_get_temp_dir(), 'fullsystem');

    $tester = cli(['path' => $file]);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('Not a directory');

    unlink($file);
});

it('creates nothing during a dry run', function () {
    $parent = tempDirectory();
    $target = $parent.'/app';

    $tester = cli(['path' => $target, '--dry-run' => true]);

    expect(is_dir($target))->toBeFalse()
        ->and($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('would create');
});

/**
 * A directory that was just created is empty, so there is a project to start
 * before anything can be installed — which needs the recipe to say which one.
 */
it('offers to start a project in a fresh directory', function () {
    $parent = tempDirectory();

    $tester = cli(['path' => $parent.'/app']);

    expect($tester->getStatusCode())->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('does not say which driver');
});
