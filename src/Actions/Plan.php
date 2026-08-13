<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Schema\InvalidSchema;
use FullSystem\Install\Schema\Schema;

/**
 * What a recipe asked for, resolved against what the driver can do.
 *
 * Every action is checked here, before the first one runs. A recipe declaring
 * something the driver cannot execute is refused whole rather than partway
 * through — being told at action four that action five is unknown would leave
 * the project half-prepared.
 */
final readonly class Plan
{
    /** The phases a recipe may declare. `install` belongs to the driver. */
    public const array PHASES = ['pre-install', 'post-install'];

    /**
     * @param  array<string, list<Action>>  $phases
     */
    private function __construct(private array $phases) {}

    /**
     * @param  list<string>  $known  action names the driver can execute
     */
    public static function from(Schema $schema, array $known): self
    {
        $phases = [];

        foreach ($schema->phases as $phase => $items) {
            if (! is_string($phase) || ! in_array($phase, self::PHASES, true)) {
                throw new InvalidSchema(
                    'Unknown phase "'.(is_string($phase) ? $phase : get_debug_type($phase)).'". '.
                    'A recipe declares '.implode(' and ', self::PHASES).'; install is the driver copying source over the project.'
                );
            }

            if (! is_array($items) || ! array_is_list($items)) {
                throw new InvalidSchema("The phase \"{$phase}\" must be a list of actions.");
            }

            $phases[$phase] = self::actionsOf($phase, $items, $known);
        }

        return new self($phases);
    }

    /**
     * @return list<Action>
     */
    public function actions(string $phase): array
    {
        return $this->phases[$phase] ?? [];
    }

    public function count(): int
    {
        return array_sum(array_map('count', $this->phases));
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @param  list<mixed>  $items
     * @param  list<string>  $known
     * @return list<Action>
     */
    private static function actionsOf(string $phase, array $items, array $known): array
    {
        $actions = [];

        foreach ($items as $index => $item) {
            $position = "{$phase}, action ".($index + 1);

            if (! is_array($item) || $item === []) {
                throw new InvalidSchema("Each action is a map of one action name to its parameters ({$position}).");
            }

            /** @var list<string> $named */
            $named = array_values(array_filter(
                array_keys($item),
                static fn (mixed $key): bool => is_string($key) && in_array($key, $known, true),
            ));

            if (count($named) !== 1) {
                throw new InvalidSchema($named === []
                    ? "No action the driver knows in {$position}: got ".implode(', ', array_map(strval(...), array_keys($item))).
                      '. The driver knows: '.implode(', ', $known).'.'
                    : "More than one action in {$position}: ".implode(', ', $named).'.');
            }

            $name = $named[0];
            $modifiers = $item;
            unset($modifiers[$name]);

            $actions[] = new Action($name, $item[$name], $modifiers);
        }

        return $actions;
    }
}
