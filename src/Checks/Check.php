<?php

declare(strict_types=1);

namespace FullSystem\Install\Checks;

use FullSystem\Install\Context;
use FullSystem\Install\Result;

interface Check
{
    public function name(): string;

    /**
     * Whether the user may knowingly continue past a failure.
     *
     * False for the checks describing what the command needs to work at all;
     * true for the ones describing risk, which are asked rather than enforced.
     */
    public function forceable(): bool;

    public function run(Context $context): Result;
}
