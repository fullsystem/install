<?php

declare(strict_types=1);

namespace FullSystem\Install\Actions;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\Filesystem;
use FullSystem\Install\Support\SafePath;

/**
 * Deletes paths the recipe declares.
 *
 * The irreversible one. The whole list is validated before a single path
 * goes: a bad path at position seven must not leave the project with six
 * things already deleted.
 */
final class Remove implements Handler
{
    public static function name(): string
    {
        return 'remove';
    }

    public function run(Context $context, Action $action): Result
    {
        $paths = Parameters::stringList($action->parameters);

        if ($paths === []) {
            return Result::fail('remove needs at least one path.');
        }

        $unsafe = array_values(array_filter($paths, static fn (string $path): bool => ! SafePath::isSafe($path)));

        if ($unsafe !== []) {
            return Result::fail('outside the project: '.implode(', ', $unsafe));
        }

        $existing = array_values(array_filter(
            $paths,
            static fn (string $path): bool => file_exists($context->path($path)),
        ));

        if ($existing === []) {
            return Result::ok('nothing to remove');
        }

        if ($context->dryRun) {
            return Result::ok('would remove '.count($existing).": \n      ".implode("\n      ", $existing));
        }

        foreach ($existing as $path) {
            Filesystem::delete($context->path($path));
        }

        return Result::ok('removed '.count($existing).' path(s)');
    }
}
