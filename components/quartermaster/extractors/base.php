<?php
/**
 * 🗺️ QUARTERMASTER - Base Extractor
 *
 * All domain-specific extractors extend this class.
 * Each extractor knows how to get video duration from its domain.
 *
 * To add a new site:
 * 1. Create a new file in this directory named after the domain (e.g., vimeo.com.php)
 * 2. Define a class extending QM_Extractor
 * 3. Implement the extract() method
 * 4. Register it in the registry at the bottom of this file
 */

abstract class QM_Extractor {
    
    /** @var string The URL being extracted */
    protected string $url;

    public function __construct(string $url) {
        $this->url = $url;
    }

    /**
     * Extract video information from the URL.
     *
     * Must return an array with:
     *   'duration_seconds' => int    (raw seconds, required)
     *   'video_title'      => string (optional)
     *   'site_title'       => string (optional, defaults to domain)
     *
     * @throws Exception on failure
     */
    abstract public function extract(): array;

    /**
     * Human-readable name for this extractor.
     */
    abstract public function name(): string;

    /**
     * Run a shell command with timeout. Returns [stdout, stderr, exit_code].
     */
    protected function run_command(string $cmd, int $timeout = 120): array {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        
        // macOS doesn't have `timeout`, use perl one-liner as fallback
        $timeout_prefix = (PHP_OS_FAMILY === 'Darwin')
            ? "perl -e 'alarm {$timeout}; exec @ARGV' -- "
            : "timeout {$timeout} ";
        $proc = proc_open($timeout_prefix . $cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            throw new Exception("Failed to execute command");
        }
        
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        
        return [trim($stdout), trim($stderr), $code];
    }

    /**
     * Fetch a URL's HTML content via curl.
     */
    protected function fetch_html(string $url): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html === false || $code >= 400) {
            throw new Exception("Failed to fetch URL (HTTP $code)");
        }

        return $html;
    }
}

// ============================================================
// 📋 EXTRACTOR REGISTRY
// ============================================================
// Maps domain patterns to extractor class files.
// Add new extractors here as they're built.

$QM_EXTRACTORS = [
    'youtube.com'  => 'youtube.com.php',
    'youtu.be'     => 'youtube.com.php',
    // 'vimeo.com'    => 'vimeo.com.php',
    // 'dailymotion.com' => 'dailymotion.com.php',
    // 'twitch.tv'    => 'twitch.tv.php',
];

/**
 * Get the appropriate extractor for a URL.
 * Falls back to a generic yt-dlp extractor if no domain-specific one exists.
 */
function qm_get_extractor(string $url): QM_Extractor {
    global $QM_EXTRACTORS;

    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';
    $host = preg_replace('/^www\./', '', $host);

    // Check for domain-specific extractor
    foreach ($QM_EXTRACTORS as $domain => $file) {
        if ($host === $domain || str_ends_with($host, ".$domain")) {
            require_once __DIR__ . "/$file";
            $class = 'QM_Extractor_' . str_replace(['.', '-'], '_', $domain);
            if (class_exists($class)) {
                return new $class($url);
            }
        }
    }

    // Fallback: generic yt-dlp extractor (handles many sites)
    require_once __DIR__ . '/generic.php';
    return new QM_Extractor_Generic($url);
}
