<?php

declare(strict_types=1);

namespace FullSystem\Install;

/**
 * The outcome of a check or a step.
 *
 * A failure always carries a reason: it is the only thing the user sees when
 * the run stops, so there is no such thing as an unexplained failure.
 */
final readonly class Result
{
    private function __construct(public bool $ok, public ?string $reason) {}

    public static function ok(): self
    {
        return new self(true, null);
    }

    public static function fail(string $reason): self
    {
        return new self(false, $reason);
    }
}
