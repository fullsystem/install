<?php

declare(strict_types=1);

use FullSystem\Install\Actions\Plan;
use FullSystem\Install\Drivers\Laravel\LaravelReact;
use FullSystem\Install\Schema\InvalidSchema;
use FullSystem\Install\Schema\Schema;

function referenceSchema(): string
{
    return __DIR__.'/fixtures/schema.json';
}

/**
 * tests/fixtures/schema.json is the reference example of the format — what a
 * theme author copies to start from. These tests are what keep it honest: if
 * the format changes and the example does not, they fail.
 */
describe('the reference schema', function () {
    it('is valid json a theme could ship', function () {
        $schema = Schema::fromFile(referenceSchema());

        expect($schema->name)->toBe('fullsystem/starter')
            ->and($schema->version)->toBe('0.1.0')
            ->and($schema->driver)->toBe('laravel-react')
            ->and($schema->source)->toBe('stubs');
    });

    it('declares only actions the driver can execute', function () {
        $plan = Plan::from(Schema::fromFile(referenceSchema()), (new LaravelReact)->actions());

        expect($plan->isEmpty())->toBeFalse();
    });

    it('requires only checks the driver offers', function () {
        $offered = array_map(
            fn ($check) => $check->name(),
            (new LaravelReact)->optionalChecks(),
        );

        expect(Schema::fromFile(referenceSchema())->requires)->each->toBeIn($offered);
    });

    it('declares only phases a theme owns', function () {
        $phases = array_keys(Schema::fromFile(referenceSchema())->phases);

        expect($phases)->each->toBeIn(Plan::PHASES);
    });

    it('exercises every action the driver knows', function () {
        $plan = Plan::from(Schema::fromFile(referenceSchema()), (new LaravelReact)->actions());

        $used = [];

        foreach (Plan::PHASES as $phase) {
            foreach ($plan->actions($phase) as $action) {
                $used[$action->name] = true;
            }
        }

        expect(array_keys($used))->toEqualCanonicalizing((new LaravelReact)->actions());
    });

    it('shows a modifier in use', function () {
        $plan = Plan::from(Schema::fromFile(referenceSchema()), (new LaravelReact)->actions());

        $dev = array_filter(
            $plan->actions('pre-install'),
            fn ($action) => $action->modifier('dev') === true,
        );

        expect($dev)->toHaveCount(1);
    });

    it('shows the same action used more than once, which a map could not express', function () {
        $plan = Plan::from(Schema::fromFile(referenceSchema()), (new LaravelReact)->actions());

        $composer = array_filter($plan->actions('pre-install'), fn ($a) => $a->name === 'composer');

        expect($composer)->toHaveCount(2);
    });
});

describe('parsing', function () {
    it('defaults source to stubs', function () {
        expect(Schema::fromJson('{"name":"acme/theme"}')->source)->toBe(Schema::DEFAULT_SOURCE);
    });

    it('leaves the optional fields null when absent', function () {
        $schema = Schema::fromJson('{}');

        expect($schema->name)->toBeNull()
            ->and($schema->version)->toBeNull()
            ->and($schema->driver)->toBeNull()
            ->and($schema->phases)->toBe([]);
    });

    it('ignores fields of the wrong type instead of trusting them', function () {
        $schema = Schema::fromJson('{"name": 42, "source": "", "phases": "nope"}');

        expect($schema->name)->toBeNull()
            ->and($schema->source)->toBe(Schema::DEFAULT_SOURCE)
            ->and($schema->phases)->toBe([]);
    });

    it('refuses malformed json', function () {
        Schema::fromJson('{ not json');
    })->throws(InvalidSchema::class);

    it('refuses json that is not an object', function () {
        Schema::fromJson('"just a string"');
    })->throws(InvalidSchema::class);

    it('refuses a theme with no schema file', function () {
        Schema::fromFile('/no/such/schema.json');
    })->throws(InvalidSchema::class, Schema::FILE);
});
