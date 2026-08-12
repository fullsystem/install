<?php

declare(strict_types=1);

namespace FullSystem\Install;

use FullSystem\Install\Support\Git;

/**
 * Where the install happens and how it is undone.
 *
 * The work goes on its own branch, so the project the user had is still one
 * `git checkout` away the whole time. When it is done they can run the app
 * from that branch, decide, and only then does anything touch the branch they
 * started on.
 *
 * A project without git gets one, with a first commit — the installer deletes
 * files and rewrites migrations, and a failure halfway through a project with
 * no history leaves something nobody can put back.
 *
 * Nothing here ever pushes.
 */
final class Workspace
{
    public const string RESTORE_POINT = 'fullsystem/pre-install';

    public const string WORK_BRANCH = 'fullsystem/install';

    /**
     * Conventional Commits, because it is the format most likely to fit a
     * project's own history — and when it does not, an obviously prefixed
     * commit is still easy to spot and rewrite.
     */
    private const string FIRST_COMMIT = 'chore: start %s';

    public const string INSTALL_COMMIT = 'feat: install %s';

    /** Where the user was, to be put back exactly there. */
    private ?string $origin = null;

    public function __construct(private readonly Git $git = new Git) {}

    /**
     * Leaves the project on a work branch, with a named commit to return to.
     */
    public function open(Context $context): Result
    {
        if (! $this->git->isInsideWorkTree($context->cwd)) {
            $started = $this->start($context);

            if (! $started->ok) {
                return $started;
            }
        }

        if (! $this->git->hasCommits($context->cwd)) {
            $committed = $this->firstCommit($context);

            if (! $committed->ok) {
                return $committed;
            }
        }

        $position = $this->currentPosition($context);

        if ($position === self::WORK_BRANCH) {
            return Result::fail(
                'you are on '.self::WORK_BRANCH.' from an earlier install. Go back to your own branch '.
                'first — `git checkout -` — and decide what to do with that one.'
            );
        }

        $this->origin = $position;

        if (! $this->git->markPoint($context->cwd, self::RESTORE_POINT)) {
            return Result::fail('could not mark the restore point.');
        }

        if (! $this->git->createBranch($context->cwd, self::WORK_BRANCH)) {
            return Result::fail('could not create the work branch.');
        }

        return Result::ok(self::WORK_BRANCH);
    }

    /**
     * Commits what the run produced, on the work branch.
     */
    public function keep(Context $context, string $message): bool
    {
        return $this->git->commitAll($context->cwd, $message);
    }

    /**
     * Brings the work into the branch the user started on.
     */
    public function apply(Context $context, string $message): Result
    {
        $origin = $this->origin;

        if ($origin === null) {
            return Result::fail('there is no branch to apply this to.');
        }

        if (! $this->git->checkout($context->cwd, $origin)) {
            return Result::fail("could not go back to {$origin}.");
        }

        if (! $this->git->merge($context->cwd, self::WORK_BRANCH, $message)) {
            return Result::fail(
                'could not merge '.self::WORK_BRANCH." into {$origin}. The work is still on the branch."
            );
        }

        $this->git->deleteBranch($context->cwd, self::WORK_BRANCH);

        return Result::ok($origin);
    }

    /**
     * Goes back without applying anything. The branch stays, so the work is
     * still there to look at — or to merge later, by hand.
     */
    public function leave(Context $context): bool
    {
        return $this->origin !== null && $this->git->checkout($context->cwd, $this->origin);
    }

    /**
     * Undoes a run that failed: back to where the user was, with the tree as
     * they left it, and the work branch gone.
     */
    public function restore(Context $context): bool
    {
        if (! $this->git->restoreTo($context->cwd, self::RESTORE_POINT)) {
            return false;
        }

        if ($this->origin === null) {
            return true;
        }

        $this->git->checkout($context->cwd, $this->origin);
        $this->git->deleteBranch($context->cwd, self::WORK_BRANCH);

        return true;
    }

    public function origin(): ?string
    {
        return $this->origin;
    }

    public function undoCommand(): string
    {
        return 'git reset --hard '.self::RESTORE_POINT.' && git clean -fd';
    }

    public function applyCommand(): string
    {
        return 'git checkout '.($this->origin ?? 'main').' && git merge '.self::WORK_BRANCH;
    }

    /**
     * A branch name when there is one, a commit when the user is on a detached
     * HEAD — either way, somewhere to return to.
     */
    private function currentPosition(Context $context): ?string
    {
        $branch = $this->git->currentBranch($context->cwd);

        return $branch === null || $branch === 'HEAD'
            ? $this->git->head($context->cwd)
            : $branch;
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

    private function firstCommit(Context $context): Result
    {
        if (! $this->gitignored($context)) {
            return Result::fail(
                'this project has no commits and no .gitignore, so committing it would put .env and '.
                'node_modules into the history. Commit it yourself first.'
            );
        }

        $message = sprintf(self::FIRST_COMMIT, basename($context->cwd));

        return $this->git->commitAll($context->cwd, $message)
            ? Result::ok($message)
            : Result::fail('could not create the first commit.');
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
