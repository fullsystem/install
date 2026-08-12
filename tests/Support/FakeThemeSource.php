<?php

declare(strict_types=1);

namespace Tests\Support;

use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Themes\ThemeSource;
use RuntimeException;

/**
 * Answers with a schema, or throws, without touching the network.
 */
final class FakeThemeSource implements ThemeSource
{
    /** @var list<string> */
    public array $asked = [];

    private function __construct(
        private readonly ?Schema $schema,
        private readonly ?RuntimeException $failure,
    ) {}

    public static function returning(?Schema $schema = null): self
    {
        return new self($schema ?? new Schema('fullsystem/starter', '1.0.0', []), null);
    }

    public static function failing(RuntimeException $failure): self
    {
        return new self(null, $failure);
    }

    public function fetch(string $theme): Schema
    {
        $this->asked[] = $theme;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        /** @var Schema */
        return $this->schema;
    }
}
