<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;

/**
 * Deletes paths the theme declares.
 *
 * The only irreversible action, so every path is validated against the project
 * root before the first one goes.
 */
final class Remove implements Handler
{
    public static function name(): string
    {
        return 'remove';
    }

    public function run(Context $context, Action $action): Result
    {
        return Result::fail('The remove action is not implemented yet.');
    }
}
