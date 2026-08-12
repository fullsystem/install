<?php

declare(strict_types=1);

namespace FullSystem\Install\Checks;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\Git;

/**
 * Uncommitted work has nowhere to go back to.
 *
 * The rollback is `git reset --hard`, which discards whatever was not
 * committed along with everything the installer wrote. On a dirty tree that
 * means losing work that was never ours to lose.
 *
 * A project with no repository at all is not this check's problem — it gets
 * one, with a first commit, before anything is written.
 */
final class CleanWorktree implements Check
{
    public const string NAME = 'clean-worktree';

    public function __construct(private readonly Git $git = new Git) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function forceable(): bool
    {
        return true;
    }

    public function run(Context $context): Result
    {
        if (! $this->git->isInsideWorkTree($context->cwd)) {
            return Result::ok();
        }

        $dirty = $this->git->dirtyFiles($context->cwd);

        return $dirty === []
            ? Result::ok()
            : Result::fail(count($dirty).' uncommitted change(s) would be lost by a rollback');
    }
}
