<?php

declare(strict_types=1);

namespace FullSystem\Install\Themes;

use FullSystem\Install\Schema\InvalidSchema;

/**
 * Where a theme comes from.
 *
 * GitHub is the only source today. An authenticated registry for exclusive
 * themes is another implementation of this, differing in the URL it requests
 * and the header it sends.
 */
interface ThemeSource
{
    /**
     * @throws InvalidTheme|DownloadFailed|InvalidArchive|InvalidSchema
     */
    public function fetch(string $theme): FetchedTheme;
}
