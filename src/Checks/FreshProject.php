<?php

declare(strict_types=1);

namespace FullSystem\Install\Checks;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Support\Git;

/**
 * Whether this still looks like a project nobody has built on yet.
 *
 * A theme that rewrites the users migration needs this; one that only adds a
 * module does not, and would never pass it. That is why the theme requires it
 * rather than it running for everyone.
 *
 * It is a heuristic, and it is built to be one: it gathers cheap signals and
 * reports all of them, leaving the decision to whoever knows the project. A
 * false positive costs one keystroke; a false negative costs someone's work.
 */
final class FreshProject implements Check
{
    public const string NAME = 'fresh-project';

    private const string STARTER_KIT_PAGE = 'resources/js/pages/dashboard.tsx';

    private const string MODELS = 'app/Models';

    /** The only model both the skeleton and the starter kits ship. */
    private const string SHIPPED_MODEL = 'User.php';

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
        $signals = array_values(array_filter([
            $this->historyOfTheirOwn($context),
            $this->replacedStarterKitPages($context),
            $this->modelsOfTheirOwn($context),
        ]));

        if ($signals === []) {
            return Result::ok();
        }

        return Result::fail('this does not look like a fresh install ('.implode(', ', $signals).')');
    }

    /**
     * More than the initial commit means someone has been working here.
     *
     * No repository and no commits both answer null: there is nothing to
     * conclude from either, and the clean-worktree check is what cares about
     * git being there at all.
     */
    private function historyOfTheirOwn(Context $context): ?string
    {
        $commits = $this->git->commitCount($context->cwd);

        return $commits !== null && $commits > 1 ? "{$commits} commits" : null;
    }

    /**
     * The kit's own pages being gone means something already replaced them —
     * possibly an earlier run of this very command.
     */
    private function replacedStarterKitPages(Context $context): ?string
    {
        return is_file($context->path(self::STARTER_KIT_PAGE))
            ? null
            : 'starter kit pages are already gone';
    }

    /**
     * Both starter kits ship User and nothing else, so anything beside it was
     * written by somebody.
     */
    private function modelsOfTheirOwn(Context $context): ?string
    {
        $directory = $context->path(self::MODELS);

        if (! is_dir($directory)) {
            return null;
        }

        $models = array_diff(
            array_map(basename(...), glob($directory.'/*.php') ?: []),
            [self::SHIPPED_MODEL],
        );

        return $models === []
            ? null
            : count($models).' model(s) beyond '.self::SHIPPED_MODEL;
    }
}
