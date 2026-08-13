<?php

declare(strict_types=1);

namespace FullSystem\Install\Recipes;

use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\Filesystem;

/**
 * A recipe, unpacked somewhere temporary, and what it declares.
 *
 * The directory matters as much as the schema: the install phase copies the
 * recipe's source out of it.
 */
final readonly class FetchedRecipe
{
    public function __construct(
        public Schema $schema,
        public string $directory,
        private ?string $workspace = null,
    ) {}

    /**
     * Throws away the download. The recipe is read and copied during the run;
     * once it is over, keeping a copy of every recipe ever installed under the
     * temp directory helps nobody.
     */
    public function discard(): void
    {
        if ($this->workspace !== null) {
            Filesystem::delete($this->workspace);
        }
    }
}
