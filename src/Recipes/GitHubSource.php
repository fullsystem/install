<?php

declare(strict_types=1);

namespace FullSystem\Install\Recipes;

use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\Filesystem;

/**
 * Downloads a public recipe from GitHub and reads what it declares.
 *
 * Nothing in the project is touched here, so an unreachable recipe or a
 * malformed schema costs the user nothing but the download.
 */
final readonly class GitHubSource implements RecipeSource
{
    public function __construct(private RecipeDownloader $downloader = new RecipeDownloader) {}

    private const string PREFIX = 'fullsystem-recipe-';

    /** Long enough that no run still using one could be caught by it. */
    private const int STALE_AFTER = 3600;

    public function fetch(string $recipe): FetchedRecipe
    {
        $name = Recipe::fromString($recipe);

        // A run killed halfway never reaches its own cleanup.
        Filesystem::forgetOlderThan(self::PREFIX, self::STALE_AFTER);

        $workspace = Filesystem::temporaryDirectory(self::PREFIX);
        $archive = $workspace.'/recipe.zip';

        $this->downloader->download($name, $archive);

        $root = Archive::extract($archive, $workspace.'/unpacked');

        return new FetchedRecipe(Schema::fromFile($root.'/'.Schema::FILE), $root, $workspace);
    }
}
