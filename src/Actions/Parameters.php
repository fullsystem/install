<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

/**
 * Reading what a theme wrote, without trusting its shape.
 *
 * A schema is JSON from a repository: every value arrives as mixed, and a
 * handler that assumes otherwise breaks on a theme that typed a string where
 * a list belonged.
 */
final class Parameters
{
    /**
     * A single string or a list of them, as a list. Anything else is dropped.
     *
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /**
     * A map of options, with non-string keys dropped.
     *
     * @return array<string, mixed>
     */
    public static function options(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $options = [];

        foreach ($value as $key => $option) {
            if (is_string($key)) {
                $options[$key] = $option;
            }
        }

        return $options;
    }

    /**
     * The values that do not match — what to complain about.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    public static function rejecting(array $values, string $pattern): array
    {
        return array_values(array_filter(
            $values,
            static fn (string $value): bool => preg_match($pattern, $value) !== 1,
        ));
    }
}
