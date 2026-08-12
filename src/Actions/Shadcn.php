<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\ProcessRunner;
use FullSystem\Install\Support\SystemProcess;

/**
 * Runs the shadcn CLI: init with the declared preset, then add.
 *
 * The action a Vue or Livewire driver could not offer — there are no React
 * components to generate.
 */
final readonly class Shadcn implements Handler
{
    private const string VERSION = 'latest';

    /** Presets, bases, templates and component names are all plain names. */
    private const string IDENTIFIER = '/^[a-z0-9][a-z0-9-]*$/i';

    public function __construct(private ProcessRunner $processes = new SystemProcess) {}

    public static function name(): string
    {
        return 'shadcn';
    }

    public function run(Context $context, Action $action): Result
    {
        $options = Parameters::options($action->parameters);

        $identifiers = [];

        foreach (['base', 'preset', 'template'] as $key) {
            if (! isset($options[$key])) {
                continue;
            }

            if (! is_string($options[$key]) || preg_match(self::IDENTIFIER, $options[$key]) !== 1) {
                return Result::fail("not a shadcn {$key}: ".json_encode($options[$key]));
            }

            $identifiers[$key] = $options[$key];
        }

        $components = $options['components'] ?? 'all';

        if ($components !== 'all') {
            $components = Parameters::stringList($components);
            $invalid = Parameters::rejecting($components, self::IDENTIFIER);

            if ($components === [] || $invalid !== []) {
                return Result::fail('shadcn components must be "all" or a list of component names.');
            }
        }

        $commands = [
            $this->initArgs($identifiers, $options['pointer'] ?? null),
            $this->addArgs($components),
        ];

        $ran = [];

        foreach ($commands as $args) {
            $command = ['npx', '--yes', 'shadcn@'.self::VERSION, ...$args];

            if ($context->dryRun) {
                $ran[] = 'would run: '.implode(' ', $command);

                continue;
            }

            if ($this->processes->run($command, $context->cwd) !== 0) {
                return Result::fail("shadcn {$args[0]} failed");
            }

            $ran[] = implode(' ', $command);
        }

        return Result::ok(implode("\n      ", $ran));
    }

    /**
     * `-f -y --reinstall` are always passed: this must never stop to ask.
     *
     * @param  array<string, string>  $identifiers
     * @return list<string>
     */
    private function initArgs(array $identifiers, mixed $pointer): array
    {
        $args = ['init'];

        foreach (['base' => '-b', 'preset' => '-p', 'template' => '-t'] as $key => $flag) {
            if (isset($identifiers[$key])) {
                $args = [...$args, $flag, $identifiers[$key]];
            }
        }

        if (is_bool($pointer)) {
            $args[] = $pointer ? '--pointer' : '--no-pointer';
        }

        return [...$args, '-f', '-y', '--reinstall'];
    }

    /**
     * @param  'all'|list<string>  $components
     * @return list<string>
     */
    private function addArgs(string|array $components): array
    {
        return $components === 'all'
            ? ['add', '--all', '--overwrite', '--yes']
            : ['add', ...$components, '--overwrite', '--yes'];
    }
}
