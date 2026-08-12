<?php

declare(strict_types=1);

namespace FullSystem\Install\Support;

use RuntimeException;

final class Filesystem
{
    /**
     * A private directory under the system temp dir, for the theme download
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
}
