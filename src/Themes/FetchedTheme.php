<?php

declare(strict_types=1);

namespace FullSystem\Install\Themes;

use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\Filesystem;

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
        private ?string $workspace = null,
    ) {}

    /**
     * Throws away the download. The theme is read and copied during the run;
     * once it is over, keeping a copy of every theme ever installed under the
     * temp directory helps nobody.
     */
    public function discard(): void
    {
        if ($this->workspace !== null) {
            Filesystem::delete($this->workspace);
        }
    }
}
