<?php
/**
 * ⚓ THE HOARD - Command Deck (Control Panel)
 * 
 * Ye olde dashboard — the captain's view of all plundered components.
 * This page auto-discovers components and displays their status.
 */

require_once __DIR__ . '/config/hoard.php';

// Discover all registered components
$components = hoard_discover_components();

// System info
$sys = [
    'php_version'    => PHP_VERSION,
    'os'             => php_uname('s') . ' ' . php_uname('r'),
    'hostname'       => gethostname(),
    'server'         => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'hoard_root'     => HOARD_ROOT,
    'data_dir'       => HOARD_DATA_DIR,
    'disk_free'      => round(disk_free_space(HOARD_ROOT) / 1073741824, 1) . ' GB',
    'disk_total'     => round(disk_total_space(HOARD_ROOT) / 1073741824, 1) . ' GB',
    'memory_usage'   => round(memory_get_usage(true) / 1048576, 1) . ' MB',
    'uptime'         => @file_get_contents('/proc/uptime') ?: shell_exec('sysctl -n kern.boottime 2>/dev/null'),
];

// Parse macOS boot time for uptime
$boot_epoch = null;
if ($sys['uptime'] && preg_match('/sec\s*=\s*(\d+)/', $sys['uptime'], $m)) {
    $boot_epoch = (int)$m[1];
    $uptime_secs = time() - $boot_epoch;
    $days = floor($uptime_secs / 86400);
    $hours = floor(($uptime_secs % 86400) / 3600);
    $sys['uptime'] = "{$days}d {$hours}h";
} else {
    $sys['uptime'] = 'Unknown';
}

// Count data files
$data_files = 0;
$data_size = 0;
if (is_dir(HOARD_DATA_DIR)) {
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(HOARD_DATA_DIR));
    foreach ($iter as $file) {
        if ($file->isFile()) {
            $data_files++;
            $data_size += $file->getSize();
        }
    }
}

// Read recent log entries
$log_entries = [];
$log_file = HOARD_LOG_DIR . '/hoard.log';
if (file_exists($log_file)) {
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_entries = array_slice($lines, -15); // Last 15 entries
    $log_entries = array_reverse($log_entries);
}

// Log this dashboard visit
hoard_log("Command Deck accessed from {$_SERVER['REMOTE_ADDR']}");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= HOARD_SITE_TITLE ?> — Command Deck</title>
    <link rel="stylesheet" href="assets/css/hoard.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏴‍☠️</text></svg>">
</head>
<body>

<!-- Navigation -->
<nav class="nav-bar">
    <a href="/" class="nav-brand">
        <span>🏴‍☠️</span> THE HOARD
    </a>
    <div class="nav-status">
        <div class="nav-clock" id="ship-clock">--:--:-- · Loading...</div>
        <div class="nav-uptime">
            <div class="pulse-dot"></div>
            <span id="uptime" data-start="<?= $boot_epoch ?: time() ?>">Calculating...</span>
        </div>
    </div>
</nav>

<main class="dashboard">

    <!-- Banner -->
    <div class="pirate-banner">
        <div class="skull">☠️</div>
        <h2>Welcome Aboard, Captain</h2>
        <p>"Take what ye can, give nothin' back." — The Hoard gathers and keeps.</p>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon">⚓</div>
            <div class="stat-value"><?= count($components) ?></div>
            <div class="stat-label">Components</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= $data_files ?></div>
            <div class="stat-label">Data Files</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💾</div>
            <div class="stat-value"><?= $data_size > 0 ? round($data_size / 1048576, 1) . 'M' : '0' ?></div>
            <div class="stat-label">Data Stored</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🏴‍☠️</div>
            <div class="stat-value"><?= $sys['disk_free'] ?></div>
            <div class="stat-label">Disk Free</div>
        </div>
    </div>

    <!-- Fleet (Components) -->
    <div class="dashboard-header">
        <h1>🚢 The Fleet</h1>
        <p>Yer active components and their status at a glance.</p>
    </div>

    <?php if (empty($components)): ?>
    <div class="empty-fleet">
        <div class="icon">🏝️</div>
        <p>No components deployed yet. The seas are empty, Captain.<br>
        New modules will appear here as they're built and registered.</p>
    </div>
    <?php else: ?>
    <div class="components-grid">
        <?php foreach ($components as $slug => $comp): ?>
        <div class="component-card" data-health-url="components/<?= htmlspecialchars($slug) ?>/health.php">
            <div class="component-header">
                <div class="component-icon"><?= $comp['icon'] ?? '📦' ?></div>
                <div class="component-info">
                    <h3><?= htmlspecialchars($comp['name'] ?? $slug) ?></h3>
                    <span class="version">v<?= htmlspecialchars($comp['version'] ?? '0.0.0') ?></span>
                </div>
            </div>
            <p class="component-desc"><?= htmlspecialchars($comp['description'] ?? 'No description.') ?></p>
            <div class="component-footer">
                <?php
                    $status = $comp['status'] ?? 'inactive';
                    $status_class = "status-$status";
                    $status_label = ucfirst($status);
                ?>
                <span class="status-badge <?= $status_class ?>">
                    <?php if ($status === 'active'): ?><span class="pulse-dot"></span><?php endif; ?>
                    <?= $status_label ?>
                </span>
                <?php if ($status === 'active'): ?>
                <a href="components/<?= htmlspecialchars($slug) ?>/" class="component-link">Open →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- System Info -->
    <div class="system-panel">
        <h2>🧭 Ship's Manifest</h2>
        <div class="system-grid">
            <div class="sys-item">
                <span class="label">Hostname</span>
                <span class="value"><?= htmlspecialchars($sys['hostname']) ?></span>
            </div>
            <div class="sys-item">
                <span class="label">OS</span>
                <span class="value"><?= htmlspecialchars($sys['os']) ?></span>
            </div>
            <div class="sys-item">
                <span class="label">PHP</span>
                <span class="value"><?= $sys['php_version'] ?></span>
            </div>
            <div class="sys-item">
                <span class="label">Server</span>
                <span class="value"><?= htmlspecialchars($sys['server']) ?></span>
            </div>
            <div class="sys-item">
                <span class="label">Hoard Root</span>
                <span class="value"><?= htmlspecialchars($sys['hoard_root']) ?></span>
            </div>
            <div class="sys-item">
                <span class="label">Uptime</span>
                <span class="value"><?= $sys['uptime'] ?></span>
            </div>
            <div class="sys-item">
                <span class="label">Disk</span>
                <span class="value"><?= $sys['disk_free'] ?> / <?= $sys['disk_total'] ?></span>
            </div>
            <div class="sys-item">
                <span class="label">Memory</span>
                <span class="value"><?= $sys['memory_usage'] ?></span>
            </div>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="activity-panel">
        <h2>📜 Captain's Log</h2>
        <?php if (empty($log_entries)): ?>
        <p style="color: var(--text-muted); font-size: 0.85rem;">No entries in the log yet. Activity will appear here as components report in.</p>
        <?php else: ?>
        <div class="log-entries">
            <?php foreach ($log_entries as $entry): ?>
            <?php
                // Parse log format: [2024-01-01 12:00:00] [INFO] Message
                if (preg_match('/\[(.+?)\]\s*\[(.+?)\]\s*(.+)/', $entry, $m)) {
                    $time = $m[1];
                    $level = $m[2];
                    $msg = $m[3];
                } else {
                    $time = '';
                    $level = '';
                    $msg = $entry;
                }
            ?>
            <div class="log-entry">
                <span class="log-time"><?= htmlspecialchars($time) ?></span>
                <span class="log-msg"><?= htmlspecialchars($msg) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</main>

<footer class="footer">
    ⚓ The Hoard · Built for plunder · <?= date('Y') ?>
</footer>

<script src="assets/js/hoard.js"></script>
</body>
</html>
