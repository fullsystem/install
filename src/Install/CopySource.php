<?php

declare(strict_types=1);

namespace FullSystem\Install\Install;

use FullSystem\Install\Context;
use FullSystem\Install\Result;
use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\SafePath;

/**
 * The install phase: the theme's files land on the project.
 *
 * The driver's own, not something a theme declares — a theme that could
 * decide when this happens could put it before the deletions that clear the
 * way for it.
 *
 * The source directory mirrors the project root, so stubs/resources/js/app.tsx
 * becomes resources/js/app.tsx. No path mapping, nothing to configure.
 */
final class CopySource
{
    public function run(Context $context, Schema $schema, string $themeDirectory): Result
    {
        if (! SafePath::isSafe($schema->source)) {
            return Result::fail("the theme's source is not a path inside itself: {$schema->source}");
        }

        $from = rtrim($themeDirectory, '/').'/'.SafePath::normalize($schema->source);

        if (! is_dir($from)) {
            return Result::fail("the theme declares {$schema->source}/ but does not ship it");
        }

        $files = $this->filesIn($from);

        if ($files === []) {
            return Result::fail("the theme's {$schema->source}/ is empty");
        }

        if ($context->dryRun) {
            return Result::ok('would copy '.count($files).' file(s) from '.$schema->source.'/');
        }

        foreach ($files as $file) {
            $target = $context->path($file);

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }

            if (! copy($from.'/'.$file, $target)) {
                return Result::fail("could not write {$file}");
            }
        }

        return Result::ok('copied '.count($files).' file(s)');
    }

    /**
     * Every file under the directory, as paths relative to it.
     *
     * Entries are read through the same guard as everything else a theme
     * names: the archive was validated on extraction, and this is the second
     * place the same rule applies.
     *
     * @return list<string>
     */
    private function filesIn(string $directory): array
    {
        $files = [];
        $prefix = strlen(rtrim($directory, '/')) + 1;

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $relative = substr($item->getPathname(), $prefix);

            if (SafePath::isSafe($relative)) {
                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }
}
