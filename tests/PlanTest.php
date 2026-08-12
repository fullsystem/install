<?php

declare(strict_types=1);

use FullSystem\Install\Actions\Plan;
use FullSystem\Install\Schema\InvalidSchema;
use FullSystem\Install\Schema\Schema;

function planFrom(array $phases, array $known = ['composer', 'packages', 'remove', 'shadcn', 'artisan']): Plan
{
    return Plan::from(new Schema('acme/theme', '1.0.0', $phases), $known);
}

it('reads the actions of a phase in the order they were declared', function () {
    $plan = planFrom(['pre-install' => [
        ['remove' => ['routes/web.php']],
        ['composer' => ['laravel/reverb']],
        ['remove' => ['components.json']],
    ]]);

    expect(array_map(fn ($a) => $a->name, $plan->actions('pre-install')))
        ->toBe(['remove', 'composer', 'remove']);
});

it('keeps the parameters of each action', function () {
    $plan = planFrom(['pre-install' => [
        ['composer' => ['laravel/reverb', 'laravel/horizon']],
    ]]);

    expect($plan->actions('pre-install')[0]->parameters)->toBe(['laravel/reverb', 'laravel/horizon']);
});

it('separates modifiers from the action', function () {
    $plan = planFrom(['pre-install' => [
        ['composer' => ['pestphp/pest'], 'dev' => true],
    ]]);

    $action = $plan->actions('pre-install')[0];

    expect($action->name)->toBe('composer')
        ->and($action->parameters)->toBe(['pestphp/pest'])
        ->and($action->modifiers)->toBe(['dev' => true]);
});

it('finds the action whichever order the keys are in', function () {
    $plan = planFrom(['pre-install' => [
        ['dev' => true, 'composer' => ['pestphp/pest']],
    ]]);

    expect($plan->actions('pre-install')[0]->name)->toBe('composer');
});

it('is empty when the theme declares no phases', function () {
    expect(planFrom([])->isEmpty())->toBeTrue()
        ->and(planFrom([])->count())->toBe(0);
});

it('counts every action across phases', function () {
    $plan = planFrom([
        'pre-install' => [['remove' => ['a']], ['composer' => ['b/c']]],
        'post-install' => [['artisan' => ['migrate']]],
    ]);

    expect($plan->count())->toBe(3);
});

describe('what it refuses', function () {
    it('refuses an action the driver does not know', function () {
        planFrom(['pre-install' => [['docker' => ['up']]]]);
    })->throws(InvalidSchema::class, 'docker');

    it('names the phase and position of the bad action', function () {
        planFrom(['post-install' => [['artisan' => ['migrate']], ['nope' => []]]]);
    })->throws(InvalidSchema::class, 'post-install');

    it('refuses an item with no action at all', function () {
        planFrom(['pre-install' => [['dev' => true]]]);
    })->throws(InvalidSchema::class);

    it('refuses an item declaring two actions', function () {
        planFrom(['pre-install' => [['composer' => ['a/b'], 'remove' => ['c']]]]);
    })->throws(InvalidSchema::class);

    it('refuses an item that is not a map', function () {
        planFrom(['pre-install' => ['composer']]);
    })->throws(InvalidSchema::class);

    it('refuses a phase that is not a list', function () {
        planFrom(['pre-install' => ['composer' => ['a/b']]]);
    })->throws(InvalidSchema::class);

    /**
     * install is the driver copying source/ over the project. A theme that
     * declares it is describing something it does not control.
     */
    it('refuses a phase the theme does not own', function (string $phase) {
        planFrom([$phase => [['remove' => ['a']]]]);
    })->throws(InvalidSchema::class)->with(['install', 'verify', 'whenever']);
});
