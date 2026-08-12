<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;

/**
 * Runs the shadcn CLI: init with the declared preset, then add.
 *
 * The action a Vue or Livewire driver could not offer — there are no React
 * components to generate.
 */
final class Shadcn implements Handler
{
    public static function name(): string
    {
        return 'shadcn';
    }

    public function run(Context $context, Action $action): Result
    {
        return Result::fail('The shadcn action is not implemented yet.');
    }
}
