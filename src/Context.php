<?php

declare(strict_types=1);

namespace FullSystem\Install;

/**
 * What the run knows about itself.
 *
 * Only what the user asked for so far. Everything the steps discover — the
 * recipe's schema, where it was unpacked, the commit to roll back to — gets
 * added as those steps come to exist.
 */
final readonly class Context
{
    public function __construct(
        public string $cwd,
        public string $recipe,
        public bool $dryRun = false,
        public bool $force = false,
    ) {}

    public function path(string $relative): string
    {
        return rtrim($this->cwd, '/').'/'.ltrim($relative, '/');
    }
}
