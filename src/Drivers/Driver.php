<?php

declare(strict_types=1);

namespace FullSystem\Install\Drivers;

use FullSystem\Install\Checks\Check;
use FullSystem\Install\Context;

/**
 * A kind of installation, not a framework: `laravel-react` and `laravel-vue`
 * are separate drivers even though the backend is the same one.
 *
 * The driver owns the execution line. It decides which steps run and in what
 * order, because that order is a property of the environment and not of the
 * theme — composer has to run before the routes are deleted, the shadcn
 * output has to land before the theme's own files overwrite it. A theme that
 * could reorder these would only gain the ability to get them wrong.
 *
 * What a theme declares is the content of each phase. What the driver decides
 * is the sequence.
 */
interface Driver
{
    /**
     * The identifier a theme declares and `--driver` accepts, e.g. `laravel-react`.
     */
    public function name(): string;

    /**
     * Whether this driver recognises the project in front of it.
     */
    public function detect(Context $context): bool;

    /**
     * What must be true before anything is written, for every theme.
     *
     * @return list<Check>
     */
    public function checks(): array;

    /**
     * Checks a theme may ask for by name in `requires`.
     *
     * Some conditions belong to the theme rather than to the environment: a
     * theme that rewrites the users migration needs a fresh project, and one
     * that only adds a module would never pass that check.
     *
     * @return list<Check>
     */
    public function optionalChecks(): array;

    /**
     * The actions a theme may declare, by name.
     *
     * A theme asking for something absent from this list is refused: a driver
     * without shadcn cannot quietly skip it and hand back a project missing
     * the components the theme assumed.
     *
     * @return list<string>
     */
    public function actions(): array;
}
