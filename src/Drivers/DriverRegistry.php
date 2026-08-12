<?php

declare(strict_types=1);

namespace FullSystem\Install\Drivers;

use FullSystem\Install\Context;
use FullSystem\Install\Drivers\Laravel\LaravelReact;

/**
 * Holds the known drivers and answers which one to use.
 *
 * Resolution order is deliberate: the project decides, the theme declares
 * what it is compatible with, and the flag is the escape hatch for when the
 * detection is wrong. Cross-checking those is the point — a theme built for
 * laravel-react pointed at a Vue project should stop before the first
 * destructive step, not halfway through it.
 */
final readonly class DriverRegistry
{
    /**
     * @param  list<Driver>  $drivers
     */
    public function __construct(private array $drivers) {}

    public static function default(): self
    {
        return new self([new LaravelReact]);
    }

    public function get(string $name): ?Driver
    {
        foreach ($this->drivers as $driver) {
            if ($driver->name() === $name) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * The first driver that recognises the project, or null when none does.
     */
    public function detect(Context $context): ?Driver
    {
        foreach ($this->drivers as $driver) {
            if ($driver->detect($context)) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (Driver $driver): string => $driver->name(), $this->drivers);
    }
}
