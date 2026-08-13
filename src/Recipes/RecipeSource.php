<?php

declare(strict_types=1);

namespace FullSystem\Install\Recipes;

use FullSystem\Install\Schema\InvalidSchema;

/**
 * Where a recipe comes from.
 *
 * GitHub is the only source today. An authenticated registry for exclusive
 * recipes is another implementation of this, differing in the URL it requests
 * and the header it sends.
 */
interface RecipeSource
{
    /**
     * @throws InvalidRecipe|DownloadFailed|InvalidArchive|InvalidSchema
     */
    public function fetch(string $recipe): FetchedRecipe;
}
