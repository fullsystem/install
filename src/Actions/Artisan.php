<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;

/**
 * Runs artisan commands.
 *
 * Built as an argument list and executed without a shell, so a value carrying
 * `;` or `\$(…)` arrives at artisan as one literal argument.
 */
final class Artisan implements Handler
{
    public static function name(): string
    {
        return 'artisan';
    }

    public function run(Context $context, Action $action): Result
    {
        return Result::fail('The artisan action is not implemented yet.');
    }
}
