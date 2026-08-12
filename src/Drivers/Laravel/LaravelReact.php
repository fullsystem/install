<?php

declare(strict_types=1);

namespace FullSystem\Install\Drivers\Laravel;

use FullSystem\Install\Actions\ActionRegistry;
use FullSystem\Install\Checks\CleanWorktree;
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
            new CleanWorktree,
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

    /**
     * Lint first, because it fixes: the theme's files arrive in the theme's
     * style, and the starter kit's own `composer test` runs `lint:check`
     * before the suite — so a formatting difference would fail an install
     * that worked.
     *
     * Then the build, which catches what the theme's TypeScript cannot
     * resolve — the usual failure when a frontend is replaced wholesale. Then
     * the suite, which catches what it broke behind: the theme ships its own
     * tests, and this is where they earn their place.
     *
     * Each step is skipped when the project does not declare it. The skeleton
     * has no `composer lint`; the React starter kit does.
     */
    public function verification(Context $context): array
    {
        $steps = [];

        if ($this->hasComposerScript($context, 'lint')) {
            $steps[] = ['label' => 'composer lint', 'command' => ['composer', 'lint']];
        }

        if ($this->hasNpmScript($context, 'build')) {
            $steps[] = ['label' => 'npm run build', 'command' => ['npm', 'run', 'build']];
        }

        if ($this->hasComposerScript($context, 'test')) {
            $steps[] = ['label' => 'composer test', 'command' => ['composer', 'test']];
        }

        return $steps;
    }

    private function hasComposerScript(Context $context, string $script): bool
    {
        return $this->declares($context->path('composer.json'), 'scripts', $script);
    }

    private function hasNpmScript(Context $context, string $script): bool
    {
        return $this->declares($context->path('package.json'), 'scripts', $script);
    }

    private function declares(string $manifest, string $section, string $key): bool
    {
        if (! is_file($manifest)) {
            return false;
        }

        $contents = file_get_contents($manifest);

        if ($contents === false) {
            return false;
        }

        /** @var mixed $data */
        $data = json_decode($contents, true);

        return is_array($data)
            && isset($data[$section])
            && is_array($data[$section])
            && array_key_exists($key, $data[$section]);
    }
}
