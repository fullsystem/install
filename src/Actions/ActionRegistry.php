<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use ReflectionClass;

/**
 * The handlers that exist, discovered from this directory.
 *
 * Discovery is by interface, not by filename: Action and Plan live here too
 * and are not handlers.
 *
 * This answers "which actions exist", which is not the same question as
 * "which actions can this driver execute" — a driver without shadcn has to
 * narrow what it takes from here, or it will accept a schema it cannot run.
 */
final class ActionRegistry
{
    /** @var array<string, class-string<Handler>>|null */
    private static ?array $handlers = null;

    /**
     * @return array<string, class-string<Handler>>
     */
    public static function handlers(): array
    {
        return self::$handlers ??= self::discover();
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::handlers());
    }

    /**
     * @return class-string<Handler>|null
     */
    public static function handlerFor(string $name): ?string
    {
        return self::handlers()[$name] ?? null;
    }

    /**
     * Forgets the cache. Only tests need this.
     */
    public static function refresh(): void
    {
        self::$handlers = null;
    }

    /**
     * @return array<string, class-string<Handler>>
     */
    private static function discover(): array
    {
        $handlers = [];

        foreach (glob(__DIR__.'/*.php') ?: [] as $file) {
            $class = __NAMESPACE__.'\\'.basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, Handler::class)) {
                continue;
            }

            if (! (new ReflectionClass($class))->isInstantiable()) {
                continue;
            }

            /** @var class-string<Handler> $class */
            $handlers[$class::name()] = $class;
        }

        return $handlers;
    }
}
