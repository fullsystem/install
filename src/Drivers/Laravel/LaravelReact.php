<?php

declare(strict_types=1);

namespace FullSystem\Install\Drivers\Laravel;

use FullSystem\Install\Actions\ActionRegistry;
use FullSystem\Install\Checks\FreshProject;
use FullSystem\Install\Context;
use FullSystem\Install\Drivers\Driver;

/**
 * Laravel with Inertia and React — the only driver that exists so far.
 *
 * Nothing is wired yet: the phases below name what will run in them and in
 * what order, and the reason each one sits where it does.
 */
final class LaravelReact implements Driver
{
    public const string NAME = 'laravel-react';

    /** The package that tells this variant apart from laravel-vue. */
    private const string SIGNATURE = '@inertiajs/react';

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Two layers: `artisan` says Laravel, the Inertia adapter says React.
     *
     * A project straight out of `laravel new` without a starter kit has
     * neither adapter installed, so nothing is detected and the theme's own
     * declaration decides.
     */
    public function detect(Context $context): bool
    {
        if (! is_file($context->path('artisan')) || ! is_file($context->path('composer.json'))) {
            return false;
        }

        $package = $context->path('package.json');

        return is_file($package)
            && str_contains((string) file_get_contents($package), self::SIGNATURE);
    }

    public function checks(): array
    {
        return [
            // clean-worktree   there is a commit to roll back to — arrives with
            //                  the actions that can shell out to git
        ];
    }

    public function optionalChecks(): array
    {
        return [
            new FreshProject,
        ];
    }

    /**
     * Everything the package ships handlers for.
     *
     * This driver happens to support all of them. A driver that does not —
     * laravel-vue has no shadcn to run — has to narrow this list, otherwise it
     * accepts a schema it cannot execute and hands back a project missing what
     * the theme assumed.
     *
     * Copying source over the project and proving the result builds are the
     * driver's own; a theme does not declare them.
     *
     * @return list<string>
     */
    public function actions(): array
    {
        return ActionRegistry::names();
    }
}
