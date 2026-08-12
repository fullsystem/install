<?php

declare(strict_types=1);

namespace FullSystem\Install;

use FullSystem\Install\Support\Git;

/**
 * Somewhere to go back to.
 *
 * The installer deletes files and rewrites migrations. Without a commit to
 * restore, a failure halfway through leaves a project nobody can put back —
 * so a project without git gets one, rather than being refused.
 *
 * Nothing here ever pushes.
 */
final readonly class RestorePoint
{
    public const string NAME = 'fullsystem/pre-install';

    private const string FIRST_COMMIT = 'Initial commit (made by fullsystem/install)';

    public function __construct(private Git $git = new Git) {}

    /**
     * Leaves the project with a named commit to return to, or explains why it
     * could not.
     */
    public function establish(Context $context): Result
    {
        if (! $this->git->isInsideWorkTree($context->cwd)) {
            $started = $this->start($context);

            if (! $started->ok) {
                return $started;
            }
        }

        if (! $this->git->hasCommits($context->cwd)) {
            if (! $this->gitignored($context)) {
                return Result::fail(
                    'this project has no commits and no .gitignore, so committing it would put .env and '.
                    'node_modules into the history. Commit it yourself first.'
                );
            }

            if (! $this->git->commitAll($context->cwd, self::FIRST_COMMIT)) {
                return Result::fail('could not create the first commit.');
            }
        }

        if (! $this->git->markPoint($context->cwd, self::NAME)) {
            return Result::fail('could not mark the restore point.');
        }

        return Result::ok(self::NAME);
    }

    /**
     * Puts everything back, including files the run created.
     */
    public function restore(Context $context): bool
    {
        return $this->git->restoreTo($context->cwd, self::NAME);
    }

    public function undoCommand(): string
    {
        return 'git reset --hard '.self::NAME.' && git clean -fd';
    }

    private function start(Context $context): Result
    {
        if (! $this->gitignored($context)) {
            return Result::fail(
                'this project has no git and no .gitignore. Starting a repository here would commit '.
                '.env and node_modules. Add a .gitignore, or run git init yourself.'
            );
        }

        return $this->git->init($context->cwd)
            ? Result::ok()
            : Result::fail('could not start a git repository here.');
    }

    /**
     * A .gitignore has to exist before `git add -A` is safe: the Laravel one
     * excludes .env, /vendor and /node_modules, and without it the first
     * commit would carry credentials.
     */
    private function gitignored(Context $context): bool
    {
        return is_file($context->path('.gitignore'));
    }
}
