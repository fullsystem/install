<?php

declare(strict_types=1);

namespace FullSystem\Install\Drivers;

use FullSystem\Install\Checks\FreshProject;
use FullSystem\Install\Context;

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
     * composer   `composer require`, with the dev modifier
     * packages   the JS dependencies, through whichever manager the project
     *            already uses — the lockfile decides, not the theme
     * remove     delete paths, validated against the project root
     * shadcn     `shadcn init` and `add`; the reason a Vue driver could not
     *            accept the same schema
     * artisan    `php artisan …`
     *
     * Copying source over the project and proving the result builds are the
     * driver's own; a theme does not declare them.
     *
     * @return list<string>
     */
    public function actions(): array
    {
        return ['composer', 'packages', 'remove', 'shadcn', 'artisan'];
    }
}
