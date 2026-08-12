<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\ProcessRunner;
use FullSystem\Install\Support\SystemProcess;

/**
 * Installs JavaScript packages.
 *
 * Which manager runs is decided here, from the lockfile the project already
 * has — a theme naming npm would be wrong in a pnpm project, and the theme has
 * no way of knowing which one it landed in.
 */
final readonly class Packages implements Handler
{
    private const string PACKAGE = '/^(@[a-z0-9][\w.-]*\/)?[a-z0-9][\w.-]*(@\S+)?$/i';

    /** Lockfile → [manager, install verb, dev flag]. Order is the precedence. */
    private const array MANAGERS = [
        'pnpm-lock.yaml' => ['pnpm', 'add', '-D'],
        'yarn.lock' => ['yarn', 'add', '-D'],
        'bun.lockb' => ['bun', 'add', '-d'],
        'package-lock.json' => ['npm', 'install', '--save-dev'],
    ];

    private const array FALLBACK = ['npm', 'install', '--save-dev'];

    public function __construct(private ProcessRunner $processes = new SystemProcess) {}

    public static function name(): string
    {
        return 'packages';
    }

    public function run(Context $context, Action $action): Result
    {
        $packages = Parameters::stringList($action->parameters);

        if ($packages === []) {
            return Result::fail('packages needs at least one package.');
        }

        $invalid = Parameters::rejecting($packages, self::PACKAGE);

        if ($invalid !== []) {
            return Result::fail('not a package name: '.implode(', ', $invalid));
        }

        [$manager, $verb, $devFlag] = $this->managerFor($context);

        $command = [
            $manager,
            $verb,
            ...($action->modifier('dev') === true ? [$devFlag] : []),
            ...$packages,
        ];

        if ($context->dryRun) {
            return Result::ok('would run: '.implode(' ', $command));
        }

        return $this->processes->run($command, $context->cwd) === 0
            ? Result::ok(implode(' ', $command))
            : Result::fail(implode(' ', $command).' failed');
    }

    /**
     * @return array{string, string, string}
     */
    private function managerFor(Context $context): array
    {
        foreach (self::MANAGERS as $lockfile => $manager) {
            if (is_file($context->path($lockfile))) {
                return $manager;
            }
        }

        return self::FALLBACK;
    }
}
