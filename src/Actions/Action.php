<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

/**
 * One line of a phase: what to do, with what, and how.
 *
 * An item declares exactly one action; every other key is a modifier of it,
 * which is what lets `['composer' => [...], 'dev' => true]` read the way it
 * does without the position of a value carrying meaning.
 */
final readonly class Action
{
    /**
     * @param  array<string, mixed>  $modifiers
     */
    public function __construct(
        public string $name,
        public mixed $parameters,
        public array $modifiers = [],
    ) {}

    public function modifier(string $name, mixed $default = null): mixed
    {
        return $this->modifiers[$name] ?? $default;
    }

    /**
     * A one-line description for the plan, before the actions know how to
     * describe themselves.
     */
    public function summary(): string
    {
        $summary = match (true) {
            is_array($this->parameters) && array_is_list($this->parameters) => match (count($this->parameters)) {
                0 => '',
                1 => (string) json_encode($this->parameters[0]),
                default => count($this->parameters).' items',
            },
            is_array($this->parameters) => implode(', ', array_keys($this->parameters)),
            is_scalar($this->parameters) => (string) $this->parameters,
            default => '',
        };

        foreach ($this->modifiers as $key => $value) {
            $summary .= $value === true ? " ({$key})" : " ({$key}: ".json_encode($value).')';
        }

        return trim($summary);
    }
}
