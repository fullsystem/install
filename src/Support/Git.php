<?php

declare(strict_types=1);

namespace FullSystem\Install\Support;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * The git the installer needs: enough to know where it is and to put it back.
 *
 * Every command is an argument list, never a string — nothing here reaches a
 * shell. And nothing here ever pushes.
 */
class Git
{
    private const int TIMEOUT = 30;

    /** The commit the installer makes carries its own identity, so a machine without git config still works. */
    private const array IDENTITY = [
        '-c', 'user.name=fullsystem/install',
        '-c', 'user.email=install@fullsystem.local',
    ];

    /**
     * Whether this path is inside a repository — which may be one several
     * directories up, in a monorepo. Asking git rather than looking for a
     * .git directory is what keeps us from nesting a repository inside
     * another one.
     */
    public function isInsideWorkTree(string $cwd): bool
    {
        return $this->capture(['git', 'rev-parse', '--is-inside-work-tree'], $cwd) === 'true';
    }

    public function hasCommits(string $cwd): bool
    {
        return $this->commitCount($cwd) !== null;
    }

    /**
     * How many commits the current branch has, or null when the question does
     * not apply — not a repository, or no commits yet.
     */
    public function commitCount(string $cwd): ?int
    {
        $output = $this->capture(['git', 'rev-list', '--count', 'HEAD'], $cwd);

        return $output !== null && ctype_digit($output) ? (int) $output : null;
    }

    /**
     * @return list<string>
     */
    public function dirtyFiles(string $cwd): array
    {
        $output = $this->capture(['git', 'status', '--porcelain'], $cwd);

        return $output === null || $output === '' ? [] : explode("\n", $output);
    }

    public function head(string $cwd): ?string
    {
        return $this->capture(['git', 'rev-parse', 'HEAD'], $cwd);
    }

    public function currentBranch(string $cwd): ?string
    {
        return $this->capture(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $cwd);
    }

    /**
     * The branch is named rather than left to init.defaultBranch, which is
     * still master on plenty of machines — the project should not start
     * somewhere different depending on who ran the installer.
     */
    public function init(string $cwd, string $branch = 'main'): bool
    {
        return $this->succeeds(['git', 'init', '-q', '-b', $branch], $cwd)
            || $this->succeeds(['git', 'init', '-q'], $cwd);
    }

    /**
     * Stages everything git is willing to stage and commits it.
     *
     * Everything means everything not ignored — which is why the caller has
     * to know there is a .gitignore before asking for this.
     */
    public function commitAll(string $cwd, string $message): bool
    {
        return $this->succeeds(['git', 'add', '-A'], $cwd)
            && $this->succeeds(['git', ...self::IDENTITY, 'commit', '-q', '-m', $message], $cwd);
    }

    /**
     * Names the current commit so it can be found again, even if the run dies
     * halfway through. Without a name, recovering means digging in the reflog.
     */
    public function markPoint(string $cwd, string $name): bool
    {
        $this->succeeds(['git', 'branch', '-D', $name], $cwd);

        return $this->succeeds(['git', 'branch', $name], $cwd);
    }

    public function createBranch(string $cwd, string $name): bool
    {
        $this->succeeds(['git', 'branch', '-D', $name], $cwd);

        return $this->succeeds(['git', 'checkout', '-q', '-b', $name], $cwd);
    }

    public function checkout(string $cwd, string $target): bool
    {
        return $this->succeeds(['git', 'checkout', '-q', $target], $cwd);
    }

    /**
     * A merge commit rather than a fast-forward: it gives the install one
     * point in the history to point at, and one commit to revert.
     */
    public function merge(string $cwd, string $branch, string $message): bool
    {
        return $this->succeeds(
            ['git', ...self::IDENTITY, 'merge', '--no-ff', '-q', '-m', $message, $branch],
            $cwd,
        );
    }

    public function deleteBranch(string $cwd, string $name): bool
    {
        return $this->succeeds(['git', 'branch', '-D', $name], $cwd);
    }

    public function restoreTo(string $cwd, string $point): bool
    {
        return $this->succeeds(['git', 'reset', '--hard', $point], $cwd)
            && $this->succeeds(['git', 'clean', '-fd'], $cwd);
    }

    /**
     * @param  list<string>  $command
     */
    private function capture(array $command, string $cwd): ?string
    {
        $process = $this->process($command, $cwd);

        return $process?->isSuccessful() === true ? trim($process->getOutput()) : null;
    }

    /**
     * @param  list<string>  $command
     */
    private function succeeds(array $command, string $cwd): bool
    {
        return $this->process($command, $cwd)?->isSuccessful() === true;
    }

    /**
     * @param  list<string>  $command
     */
    private function process(array $command, string $cwd): ?Process
    {
        try {
            $process = new Process($command, cwd: $cwd, timeout: self::TIMEOUT);
            $process->run();

            return $process;
        } catch (ExceptionInterface) {
            return null;
        }
    }
}
