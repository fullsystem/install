<?php

declare(strict_types=1);

namespace FullSystem\Install\Support;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * The tail is what explains a failure; the rest is noise from a build that
     * was going fine until it was not.
     */
    public function tail(int $lines = 20): string
    {
        $all = array_filter(explode("\n", trim($this->output)), static fn (string $line): bool => trim($line) !== '');

        return implode("\n", array_slice($all, -$lines));
    }
}
