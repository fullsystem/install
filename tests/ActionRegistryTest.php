<?php

declare(strict_types=1);

use FullSystem\Install\Actions\ActionRegistry;
use FullSystem\Install\Actions\Artisan;
use FullSystem\Install\Actions\Composer;
use FullSystem\Install\Actions\Handler;
use FullSystem\Install\Actions\Packages;
use FullSystem\Install\Actions\Remove;
use FullSystem\Install\Actions\Shadcn;

it('finds every handler in the directory', function () {
    expect(ActionRegistry::names())
        ->toEqualCanonicalizing(['composer', 'packages', 'remove', 'shadcn', 'artisan']);
});

it('maps each name to the class that handles it', function () {
    expect(ActionRegistry::handlers())->toEqualCanonicalizing([
        'composer' => Composer::class,
        'packages' => Packages::class,
        'remove' => Remove::class,
        'shadcn' => Shadcn::class,
        'artisan' => Artisan::class,
    ]);
});

/**
 * Action and Plan live in the same directory and are not handlers. Scanning by
 * filename would pick them up; scanning by interface does not.
 */
it('ignores classes in the directory that are not handlers', function () {
    expect(ActionRegistry::names())
        ->not->toContain('Action')
        ->not->toContain('Plan');
});

it('only returns classes that implement the handler interface', function () {
    foreach (ActionRegistry::handlers() as $class) {
        expect(is_subclass_of($class, Handler::class))->toBeTrue("{$class} is not a handler");
    }
});

it('names each handler exactly once', function () {
    $names = ActionRegistry::names();

    expect(array_unique($names))->toHaveCount(count($names));
});
