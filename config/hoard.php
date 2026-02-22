<?php
/**
 * ⚓ HOARD - Global Configuration
 * 
 * Adjust these paths and settings to match yer vessel's layout.
 * All components read from this single config file.
 */

// ============================================================
// 📁 DIRECTORY PATHS - Change these to match your deployment
// ============================================================

// Root directory of the Hoard installation
define('HOARD_ROOT', dirname(__DIR__));

// Where components store their data (SQLite DBs, JSON, cached files)
define('HOARD_DATA_DIR', HOARD_ROOT . '/data');

// Where log files go
define('HOARD_LOG_DIR', HOARD_ROOT . '/logs');

// Where component modules live
define('HOARD_COMPONENTS_DIR', HOARD_ROOT . '/components');

// Assets directory (CSS, JS, images)
define('HOARD_ASSETS_DIR', HOARD_ROOT . '/assets');

// ============================================================
// 🌐 SERVER SETTINGS
// ============================================================

// Base URL for the application (no trailing slash)
define('HOARD_BASE_URL', 'http://localhost:8080');

// Site title
define('HOARD_SITE_TITLE', '⚓ The Hoard');

// Timezone
define('HOARD_TIMEZONE', 'America/New_York');

// ============================================================
// 🏴‍☠️ COMPONENT REGISTRY
// ============================================================
// Each component registers itself here.
// Format: 'slug' => ['name' => '...', 'icon' => '...', 'description' => '...', 'status' => 'active|inactive|error']
// Components will self-register via their own config, but we track them centrally here.

$HOARD_COMPONENTS = [
    // Example (will be populated as components are built):
    // 'rss-plunder' => [
    //     'name'        => 'RSS Plunder',
    //     'icon'        => '📰',
    //     'description' => 'Pillage RSS feeds and store articles',
    //     'status'      => 'active',
    //     'version'     => '1.0.0',
    // ],
];

// ============================================================
// 🔧 INTERNAL - Don't change unless ye know what ye're doing
// ============================================================

date_default_timezone_set(HOARD_TIMEZONE);

// Ensure data & log directories exist
if (!is_dir(HOARD_DATA_DIR)) mkdir(HOARD_DATA_DIR, 0755, true);
if (!is_dir(HOARD_LOG_DIR)) mkdir(HOARD_LOG_DIR, 0755, true);

// Simple logging helper
function hoard_log(string $message, string $level = 'INFO'): void {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(HOARD_LOG_DIR . '/hoard.log', $line, FILE_APPEND | LOCK_EX);
}

// Component discovery - scan components dir for manifest.json files
function hoard_discover_components(): array {
    global $HOARD_COMPONENTS;
    
    $dir = HOARD_COMPONENTS_DIR;
    if (!is_dir($dir)) return $HOARD_COMPONENTS;
    
    foreach (scandir($dir) as $slug) {
        if ($slug[0] === '.') continue;
        $manifest = "$dir/$slug/manifest.json";
        if (file_exists($manifest)) {
            $data = json_decode(file_get_contents($manifest), true);
            if ($data) {
                $HOARD_COMPONENTS[$slug] = $data;
            }
        }
    }
    
    return $HOARD_COMPONENTS;
}

// Get component status via its health endpoint
function hoard_check_component(string $slug): array {
    $health_file = HOARD_COMPONENTS_DIR . "/$slug/health.php";
    if (!file_exists($health_file)) {
        return ['status' => 'unknown', 'message' => 'No health check available'];
    }
    return include $health_file;
}
