<?php

declare(strict_types=1);

namespace FullSystem\Install;

/**
 * The outcome of a check, an action or a step.
 *
 * A failure always carries a reason: it is the only thing the user sees when
 * the run stops, so there is no such thing as an unexplained failure. A
 * success may carry a message — what was done, or what would have been.
 */
final readonly class Result
{
    private function __construct(
        public bool $ok,
        public ?string $reason,
        public ?string $message = null,
    ) {}

    public static function ok(?string $message = null): self
    {
        return new self(true, null, $message);
    }

    public static function fail(string $reason): self
    {
        return new self(false, $reason);
    }
}
