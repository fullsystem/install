<?php

declare(strict_types=1);

namespace Tests\Support;

use FullSystem\Install\Recipes\FetchedRecipe;
use FullSystem\Install\Recipes\RecipeSource;
use FullSystem\Install\Schema\Schema;
use RuntimeException;

/**
 * Answers with a schema, or throws, without touching the network.
 */
final class FakeRecipeSource implements RecipeSource
{
    /** @var list<string> */
    public array $asked = [];

    private function __construct(
        private readonly ?Schema $schema,
        private readonly ?RuntimeException $failure,
        private readonly ?string $directory = null,
    ) {}

    public static function returning(?Schema $schema = null, ?string $directory = null): self
    {
        return new self($schema ?? new Schema('fullsystem/starter-kit', '1.0.0', []), null, $directory);
    }

    public static function failing(RuntimeException $failure): self
    {
        return new self(null, $failure);
    }

    public function fetch(string $recipe): FetchedRecipe
    {
        $this->asked[] = $recipe;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        /** @var Schema $schema */
        $schema = $this->schema;

        return new FetchedRecipe($schema, $this->directory ?? self::emptyRecipe($schema->source));
    }

    /**
     * A recipe directory with one file, so the install phase has something to
     * copy when the test does not care what.
     */
    private static function emptyRecipe(string $source): string
    {
        // Not the production prefix: a test asserting the real one leaves
        // nothing behind should not be measuring the fake's own leftovers.
        $path = sys_get_temp_dir().'/fullsystem-fakerecipe-'.bin2hex(random_bytes(6));

        mkdir($path.'/'.$source.'/resources/js', 0755, true);
        file_put_contents($path.'/'.$source.'/resources/js/app.tsx', '// from the recipe');

        register_shutdown_function(static fn () => is_dir($path) && exec('rm -rf '.escapeshellarg($path)));

        return $path;
    }
}
