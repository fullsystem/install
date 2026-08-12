<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;

/**
 * Installs JavaScript packages.
 *
 * Which manager runs is the driver's business, decided by the lockfile the
 * project already has — a theme naming npm would be wrong in a pnpm project.
 */
final class Packages implements Handler
{
    public static function name(): string
    {
        return 'packages';
    }

    public function run(Context $context, Action $action): Result
    {
        return Result::fail('The packages action is not implemented yet.');
    }
}
