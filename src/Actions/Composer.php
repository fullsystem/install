<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;

/**
 * Installs PHP packages with `composer require`.
 *
 * The `dev` modifier decides whether they land in require or require-dev.
 */
final class Composer implements Handler
{
    public static function name(): string
    {
        return 'composer';
    }

    public function run(Context $context, Action $action): Result
    {
        return Result::fail('The composer action is not implemented yet.');
    }
}
