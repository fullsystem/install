<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\ProcessRunner;
use FullSystem\Install\Support\SystemProcess;

/**
 * Runs artisan commands.
 *
 * A denylist rather than an allowlist. An allowlist looked safer, but it also
 * blocked `reverb:install` and every other `*:install` a theme legitimately
 * needs, and it would never keep up with the ecosystem. What is blocked is
 * what destroys data — and that is not a security boundary either: a theme
 * that can add a Composer package can already run code. It is here to stop an
 * accident, not an attacker.
 *
 * PHP_BINARY rather than `php`: the interpreter already running is a better
 * guess than whatever the PATH resolves to.
 */
final readonly class Artisan implements Handler
{
    private const string COMMAND = '/^[a-z][a-z0-9]*(:[a-z0-9][a-z0-9-]*)*$/i';

    private const string FLAG = '/^--?[a-z0-9][a-z0-9-]*(=[\w.\/-]+)?$/i';

    /** Commands that can lose data no theme should be able to lose for you. */
    private const array DESTRUCTIVE = [
        'db:wipe',
        'migrate:fresh',
        'migrate:reset',
        'migrate:rollback',
    ];

    public function __construct(private ProcessRunner $processes = new SystemProcess) {}

    public static function name(): string
    {
        return 'artisan';
    }

    public function run(Context $context, Action $action): Result
    {
        $declared = Parameters::stringList($action->parameters);

        if ($declared === []) {
            return Result::fail('artisan needs at least one command.');
        }

        $commands = [];

        foreach ($declared as $entry) {
            $argv = preg_split('/\s+/', trim($entry), flags: PREG_SPLIT_NO_EMPTY) ?: [];
            $name = array_shift($argv);

            if ($name === null || preg_match(self::COMMAND, $name) !== 1) {
                return Result::fail('not an artisan command: '.$entry);
            }

            if (in_array(strtolower($name), self::DESTRUCTIVE, true)) {
                return Result::fail("{$name} destroys data and cannot be declared by a theme.");
            }

            $badFlags = Parameters::rejecting($argv, self::FLAG);

            if ($badFlags !== []) {
                return Result::fail("not a flag for {$name}: ".implode(' ', $badFlags));
            }

            $commands[] = [$name, ...$argv];
        }

        $ran = [];

        foreach ($commands as $argv) {
            $command = [PHP_BINARY, 'artisan', ...$argv];

            if ($context->dryRun) {
                $ran[] = 'would run: php artisan '.implode(' ', $argv);

                continue;
            }

            if ($this->processes->run($command, $context->cwd) !== 0) {
                return Result::fail('php artisan '.implode(' ', $argv).' failed');
            }

            $ran[] = 'php artisan '.implode(' ', $argv);
        }

        return Result::ok(implode("\n      ", $ran));
    }
}
