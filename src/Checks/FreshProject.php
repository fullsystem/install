<?php

declare(strict_types=1);

namespace FullSystem\Install\Checks;

use FullSystem\Install\Context;
use FullSystem\Install\Result;

/**
 * Whether this still looks like a project nobody has built on yet.
 *
 * A theme that rewrites the users migration needs this; one that only adds a
 * module does not, and would never pass it. That is why it is required by the
 * theme rather than run for everyone.
 *
 * It is a heuristic, not a guarantee. A false positive costs one keystroke;
 * a false negative costs someone's work.
 *
 * Only the starter kit pages are checked so far. The stronger signal — more
 * than one commit — needs to shell out to git, which arrives with the actions
 * that run processes.
 */
final class FreshProject implements Check
{
    public const string NAME = 'fresh-project';

    private const string STARTER_KIT_PAGE = 'resources/js/pages/dashboard.tsx';

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
        if (is_file($context->path(self::STARTER_KIT_PAGE))) {
            return Result::ok();
        }

        return Result::fail(
            'this does not look like a fresh install ('.self::STARTER_KIT_PAGE.' is already gone)'
        );
    }
}
