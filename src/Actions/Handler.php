<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;

/**
 * Executes one kind of action a recipe can declare.
 *
 * The name is static so the registry can read it without building the
 * handler — handlers will take dependencies (a process runner, a filesystem)
 * that are none of the registry's business.
 */
interface Handler
{
    /**
     * The key a recipe writes in its schema.
     */
    public static function name(): string;

    public function run(Context $context, Action $action): Result;
}
