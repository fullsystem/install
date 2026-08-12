<?php

declare(strict_types=1);

namespace FullSystem\Install\Themes;

use FullSystem\Install\Support\SafePath;
use ZipArchive;

/**
 * Unpacks a downloaded theme.
 *
 * A zip entry can name a path like `../../etc`, which would write outside the
 * destination. Every entry is checked before a single one is written, and an
 * archive with one bad entry is refused whole rather than unpacked halfway —
 * a partially unpacked hostile archive is still a hostile archive.
 */
final class Archive
{
    /**
     * Unpacks into $into and returns the directory the theme actually lives
     * in, which may be one level down.
     */
    public static function extract(string $archive, string $into): string
    {
        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            throw new InvalidArchive('The downloaded file is not a zip archive.');
        }

        try {
            $entries = self::entries($zip);

            if ($entries === []) {
                throw new InvalidArchive('The archive is empty.');
            }

            if (! $zip->extractTo($into)) {
                throw new InvalidArchive("Could not unpack the archive into {$into}.");
            }
        } finally {
            $zip->close();
        }

        return self::root($into, $entries);
    }

    /**
     * @return list<string>
     */
    private static function entries(ZipArchive $zip): array
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if ($name === false) {
                throw new InvalidArchive("Could not read entry {$index} of the archive.");
            }

            if (! SafePath::isSafe($name)) {
                throw new InvalidArchive("The archive contains an entry that escapes the destination: {$name}");
            }

            $entries[] = $name;
        }

        return $entries;
    }

    /**
     * GitHub wraps everything in `{repo}-{ref}/`. An archive served by a
     * registry may not, so the rule is structural rather than a naming
     * convention: one shared top-level directory means that is the root.
     *
     * @param  list<string>  $entries
     */
    private static function root(string $into, array $entries): string
    {
        $tops = [];

        foreach ($entries as $entry) {
            $top = explode('/', trim($entry, '/'))[0];

            if ($top !== '') {
                $tops[$top] = true;
            }
        }

        if (count($tops) !== 1) {
            return $into;
        }

        $only = $into.'/'.array_key_first($tops);

        return is_dir($only) ? $only : $into;
    }
}
