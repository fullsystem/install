<?php

declare(strict_types=1);

namespace FullSystem\Install\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class Filesystem
{
    /**
     * A private directory under the system temp dir, for the recipe download
     * and anything else that must not land in the project.
     */
    public static function temporaryDirectory(string $prefix): string
    {
        $path = sys_get_temp_dir().'/'.$prefix.bin2hex(random_bytes(6));

        if (! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create a temporary directory at {$path}.");
        }

        return $path;
    }

    public static function delete(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }

    /**
     * Clears out what earlier runs left behind.
     *
     * A run killed halfway never reaches its own cleanup, so without this the
     * temp directory collects a copy of every recipe ever downloaded. Only
     * directories with our prefix, and only ones old enough that no run could
     * still be using them.
     */
    public static function forgetOlderThan(string $prefix, int $seconds): void
    {
        $cutoff = time() - $seconds;

        foreach (glob(sys_get_temp_dir().'/'.$prefix.'*') ?: [] as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $modified = filemtime($path);

            if ($modified !== false && $modified < $cutoff) {
                self::delete($path);
            }
        }
    }
}
