<?php

declare(strict_types=1);

namespace Tests\Support;

use FullSystem\Install\Support\ProcessRunner;

/**
 * Records commands instead of running them.
 *
 * Every test that exercises a handler outside --dry-run needs this: the real
 * runner would install packages on whoever ran the suite.
 */
final class FakeProcess implements ProcessRunner
{
    /** @var list<array{command: list<string>, cwd: string}> */
    public array $calls = [];

    /** @var array<string, int> */
    private array $failures = [];

    public function fails(string $needle, int $exitCode = 1): self
    {
        $this->failures[$needle] = $exitCode;

        return $this;
    }

    public function run(array $command, string $cwd): int
    {
        $this->calls[] = ['command' => $command, 'cwd' => $cwd];

        foreach ($this->failures as $needle => $code) {
            if (str_contains(implode(' ', $command), $needle)) {
                return $code;
            }
        }

        return 0;
    }

    /**
     * @return list<list<string>>
     */
    public function commands(): array
    {
        return array_map(static fn (array $call): array => $call['command'], $this->calls);
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(static fn (array $command): string => implode(' ', $command), $this->commands());
    }
}
