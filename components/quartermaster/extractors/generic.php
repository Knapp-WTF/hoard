<?php
/**
 * 🗺️ QUARTERMASTER - Generic Extractor (Fallback)
 *
 * Uses yt-dlp which supports hundreds of sites.
 * This is the fallback when no domain-specific extractor exists.
 */

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/../config.php';

class QM_Extractor_Generic extends QM_Extractor {

    public function name(): string {
        return 'Generic (yt-dlp)';
    }

    public function extract(): array {
        $bin = escapeshellarg(QM_YTDLP_BIN);
        $escaped_url = escapeshellarg($this->url);

        $cmd = "$bin --print duration --print title --print webpage_url_domain --no-download --no-warnings $escaped_url 2>&1";

        [$stdout, $stderr, $code] = $this->run_command($cmd, QM_EXTRACT_TIMEOUT);

        if ($code !== 0) {
            throw new Exception("yt-dlp failed (code $code): $stdout $stderr");
        }

        $lines = explode("\n", $stdout);
        $duration_raw = trim($lines[0] ?? '');
        $title = trim($lines[1] ?? '');
        $domain = trim($lines[2] ?? '');

        $seconds = (int) round((float) $duration_raw);

        if ($seconds <= 0) {
            throw new Exception("Could not extract video duration from this page");
        }

        return [
            'duration_seconds' => $seconds,
            'video_title'      => $title ?: null,
            'site_title'       => $domain ?: 'Unknown',
        ];
    }
}
