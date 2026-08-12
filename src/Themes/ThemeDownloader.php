<?php

declare(strict_types=1);

namespace FullSystem\Install\Themes;

use CurlHandle;
use FullSystem\Install\Application;

/**
 * Downloads a theme archive over HTTP.
 *
 * Download rather than clone because a request has somewhere to put an
 * Authorization header — which is what exclusive themes will need — and
 * because a credential in a clone URL ends up in the shell history and in the
 * clone's own config.
 */
final class ThemeDownloader
{
    private const int CONNECT_TIMEOUT = 15;

    private const int TIMEOUT = 120;

    /**
     * @param  array<string, string>  $headers  reserved for authenticated themes
     */
    public function download(Theme $theme, string $to, array $headers = []): void
    {
        $file = fopen($to, 'wb');

        if ($file === false) {
            throw new DownloadFailed("Could not write to {$to}.");
        }

        $curl = curl_init($theme->archiveUrl());

        if ($curl === false) {
            fclose($file);

            throw new DownloadFailed('Could not start the download.');
        }

        try {
            $this->configure($curl, $file, $headers);

            // No curl_close(): deprecated in 8.5, and a no-op since 8.0 — the
            // handle is released when it goes out of scope.
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
        } finally {
            fclose($file);
        }

        $this->guard($theme, $ok, $status, $error);
    }

    /**
     * @param  resource  $file
     * @param  array<string, string>  $headers
     */
    private function configure(CurlHandle $curl, $file, array $headers): void
    {
        curl_setopt_array($curl, [
            CURLOPT_FILE => $file,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => 'fullsystem-install/'.Application::VERSION,
            CURLOPT_HTTPHEADER => array_map(
                static fn (string $key, string $value): string => "{$key}: {$value}",
                array_keys($headers),
                $headers,
            ),
        ]);
    }

    private function guard(Theme $theme, bool|string $ok, int $status, string $error): void
    {
        if ($ok === false) {
            throw new DownloadFailed("Could not reach GitHub: {$error}");
        }

        if ($status === 404) {
            throw new DownloadFailed("No such theme, or it is not public: {$theme}");
        }

        if ($status >= 400) {
            throw new DownloadFailed("GitHub answered {$status} for {$theme}.");
        }
    }
}
