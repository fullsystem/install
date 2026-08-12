<?php

declare(strict_types=1);

namespace FullSystem\Install\Support;

/**
 * Guards every path that comes from a theme — entries in an archive, paths in
 * `remove`. Absolute paths and anything climbing out with `..` are refused,
 * including the root itself, which would otherwise mean the whole project.
 */
final class SafePath
{
    public static function isSafe(string $path): bool
    {
        if ($path === '' || self::isAbsolute($path)) {
            return false;
        }

        $normalized = self::normalize($path);

        return $normalized !== '' && ! str_starts_with($normalized, '..');
    }

    /**
     * Collapses `.` and `..` textually: the path may not exist yet, so
     * realpath() is not an option.
     */
    public static function normalize(string $path): string
    {
        $segments = [];

        foreach (preg_split('#[\\\\/]+#', $path) ?: [] as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment !== '..') {
                $segments[] = $segment;

                continue;
            }

            if ($segments === []) {
                return '..';
            }

            array_pop($segments);
        }

        return implode('/', $segments);
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[a-z]:[\\\\\/]/i', $path) === 1;
    }
}
