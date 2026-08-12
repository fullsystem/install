<?php

declare(strict_types=1);

namespace FullSystem\Install\Steps;

use FullSystem\Install\Context;
use FullSystem\Install\Result;

interface Step
{
    public function name(): string;

    public function run(Context $context): Result;
}
