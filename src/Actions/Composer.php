<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\ProcessRunner;
use FullSystem\Install\Support\SystemProcess;

/**
 * Installs PHP packages with `composer require`.
 *
 * The `dev` modifier decides whether they land in require or require-dev.
 */
final readonly class Composer implements Handler
{
    /**
     * Package names are checked for shape before becoming arguments. Not
     * against a shell — there is none — but against a name like
     * `--ignore-platform-reqs`, which composer would read as a flag.
     */
    private const string PACKAGE = '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*(:\S+)?$/i';

    public function __construct(private ProcessRunner $processes = new SystemProcess) {}

    public static function name(): string
    {
        return 'composer';
    }

    public function run(Context $context, Action $action): Result
    {
        $packages = Parameters::stringList($action->parameters);

        if ($packages === []) {
            return Result::fail('composer needs at least one package.');
        }

        $invalid = Parameters::rejecting($packages, self::PACKAGE);

        if ($invalid !== []) {
            return Result::fail('not a composer package name: '.implode(', ', $invalid));
        }

        $command = [
            'composer',
            'require',
            ...($action->modifier('dev') === true ? ['--dev'] : []),
            ...$packages,
        ];

        if ($context->dryRun) {
            return Result::ok('would run: '.implode(' ', $command));
        }

        return $this->processes->run($command, $context->cwd) === 0
            ? Result::ok(implode(' ', $command))
            : Result::fail(implode(' ', $command).' failed');
    }
}
