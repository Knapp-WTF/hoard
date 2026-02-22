<?php
/**
 * 🗺️ QUARTERMASTER - YouTube Extractor
 *
 * Handles: youtube.com, youtu.be
 * Method: yt-dlp --print duration,title (fast, no download)
 */

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/../config.php';

class QM_Extractor_youtube_com extends QM_Extractor {

    public function name(): string {
        return 'YouTube';
    }

    public function extract(): array {
        // Normalize URL
        $url = $this->normalize_url($this->url);

        // Use yt-dlp to get duration and title in one call
        $bin = escapeshellarg(QM_YTDLP_BIN);
        $escaped_url = escapeshellarg($url);

        // Print duration (seconds) and title separated by newline
        $cmd = "$bin --print duration --print title --no-download --no-warnings $escaped_url 2>&1";

        [$stdout, $stderr, $code] = $this->run_command($cmd, QM_EXTRACT_TIMEOUT);

        if ($code !== 0) {
            throw new Exception("yt-dlp failed (code $code): $stdout $stderr");
        }

        $lines = explode("\n", $stdout);
        $duration_raw = trim($lines[0] ?? '');
        $title = trim($lines[1] ?? '');

        // yt-dlp returns duration as float seconds (e.g., "1587" or "1587.0")
        $seconds = (int) round((float) $duration_raw);

        if ($seconds <= 0) {
            throw new Exception("Could not parse duration from yt-dlp output: '$duration_raw'");
        }

        return [
            'duration_seconds' => $seconds,
            'video_title'      => $title ?: null,
            'site_title'       => 'YouTube',
        ];
    }

    /**
     * Normalize YouTube URLs (handle youtu.be, shorts, etc.)
     */
    private function normalize_url(string $url): string {
        // youtu.be/ID → full URL
        if (preg_match('#youtu\.be/([a-zA-Z0-9_-]+)#', $url, $m)) {
            return "https://www.youtube.com/watch?v={$m[1]}";
        }

        // youtube.com/shorts/ID → watch URL
        if (preg_match('#youtube\.com/shorts/([a-zA-Z0-9_-]+)#', $url, $m)) {
            return "https://www.youtube.com/watch?v={$m[1]}";
        }

        return $url;
    }
}
