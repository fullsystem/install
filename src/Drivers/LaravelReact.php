<?php

declare(strict_types=1);

namespace FullSystem\Install\Drivers;

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
            // laravel-project        artisan and composer.json are present
            // components-directory   there is a starter kit frontend to replace
            // clean-worktree         there is a commit to roll back to
            // fresh-project          this does not look like a project with real work in it
        ];
    }

    public function preInstall(): array
    {
        return [
            // fetch      download the theme archive, validate the whole schema
            // composer   before strip: composer boots the app to discover
            //            packages, and that fails once the routes are gone
            // npm        before shadcn: both write to package.json
        ];
    }

    public function install(): array
    {
        return [
            // strip      remove what the theme declares, plus the base set
            // shadcn     init and add with the declared preset
            // copy       after shadcn, so generated ui/ cannot overwrite the
            //            files the theme ships
        ];
    }

    public function postInstall(): array
    {
        return [
            // artisan    after copy: the files it acts on are the ones just
            //            copied in (wayfinder reads the new routes)
            // verify     npm install, then build; rolls back if it fails
        ];
    }
}
