<?php

declare(strict_types=1);

namespace FullSystem\Install\Themes;

use FullSystem\Install\Schema\Schema;

/**
 * A theme, unpacked somewhere temporary, and what it declares.
 *
 * The directory matters as much as the schema: the install phase copies the
 * theme's source out of it.
 */
final readonly class FetchedTheme
{
    public function __construct(
        public Schema $schema,
        public string $directory,
    ) {}
}
