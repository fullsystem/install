<?php

declare(strict_types=1);

namespace FullSystem\Install\Drivers;

use FullSystem\Install\Checks\Check;
use FullSystem\Install\Context;
use FullSystem\Install\Steps\Step;

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
     * What must be true before anything is written.
     *
     * @return list<Check>
     */
    public function checks(): array;

    /**
     * Everything that prepares the ground and touches nothing the user owns.
     *
     * @return list<Step>
     */
    public function preInstall(): array;

    /**
     * The transformation itself. This is the driver's own; a theme adds to the
     * phases around it, not to this one.
     *
     * @return list<Step>
     */
    public function install(): array;

    /**
     * What runs once the files are in place, ending with the proof it builds.
     *
     * @return list<Step>
     */
    public function postInstall(): array;
}
