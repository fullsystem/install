<?php

declare(strict_types=1);

namespace FullSystem\Install\Themes;

use FullSystem\Install\Schema\Schema;
use FullSystem\Install\Support\Filesystem;

/**
 * Downloads a public theme from GitHub and reads what it declares.
 *
 * Nothing in the project is touched here, so an unreachable theme or a
 * malformed schema costs the user nothing but the download.
 */
final readonly class GitHubSource implements ThemeSource
{
    public function __construct(private ThemeDownloader $downloader = new ThemeDownloader) {}

    public function fetch(string $theme): Schema
    {
        $name = Theme::fromString($theme);

        $workspace = Filesystem::temporaryDirectory('fullsystem-theme-');
        $archive = $workspace.'/theme.zip';

        $this->downloader->download($name, $archive);

        $root = Archive::extract($archive, $workspace.'/unpacked');

        return Schema::fromFile($root.'/'.Schema::FILE);
    }
}
