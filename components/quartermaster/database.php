<?php
/**
 * 🗺️ QUARTERMASTER - Database Layer
 *
 * SQLite database for the queue, completed jobs, and failures.
 * Duration is always stored as integer seconds (backend-friendly).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../config/hoard.php';

function qm_get_db(): PDO {
    static $db = null;
    if ($db) return $db;

    $path = HOARD_DATA_DIR . '/' . QM_DB_FILE;
    $is_new = !file_exists($path);

    $db = new PDO("sqlite:$path", null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Enable WAL mode for better concurrent access
    $db->exec('PRAGMA journal_mode=WAL');

    if ($is_new) {
        qm_init_schema($db);
    }

    return $db;
}

function qm_init_schema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS jobs (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            url             TEXT NOT NULL,
            domain          TEXT NOT NULL,
            status          TEXT NOT NULL DEFAULT 'queued',
            -- queued | processing | completed | failed
            site_title      TEXT,
            video_title     TEXT,
            duration_seconds INTEGER,
            -- Duration in raw seconds for machine use
            duration_display TEXT,
            -- Human-readable e.g. '26m 27s'
            error_message   TEXT,
            extractor_used  TEXT,
            created_at      DATETIME DEFAULT (datetime('now','localtime')),
            started_at      DATETIME,
            completed_at    DATETIME
        );

        CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status);
        CREATE INDEX IF NOT EXISTS idx_jobs_domain ON jobs(domain);
        CREATE INDEX IF NOT EXISTS idx_jobs_created ON jobs(created_at);
    ");
}

/**
 * Add a URL to the queue. Returns the job ID.
 */
function qm_enqueue(string $url): int {
    $db = qm_get_db();

    // Extract domain
    $parsed = parse_url($url);
    $domain = $parsed['host'] ?? 'unknown';
    $domain = preg_replace('/^www\./', '', $domain);

    $stmt = $db->prepare("INSERT INTO jobs (url, domain) VALUES (?, ?)");
    $stmt->execute([$url, $domain]);

    $id = (int) $db->lastInsertId();
    hoard_log("Quartermaster: Job #$id queued — $url");

    return $id;
}

/**
 * Mark a job as processing.
 */
function qm_mark_processing(int $id): void {
    $db = qm_get_db();
    $stmt = $db->prepare("UPDATE jobs SET status='processing', started_at=datetime('now','localtime') WHERE id=?");
    $stmt->execute([$id]);
}

/**
 * Mark a job as completed with duration data.
 */
function qm_mark_completed(int $id, array $data): void {
    $db = qm_get_db();
    $stmt = $db->prepare("
        UPDATE jobs SET
            status = 'completed',
            site_title = ?,
            video_title = ?,
            duration_seconds = ?,
            duration_display = ?,
            extractor_used = ?,
            completed_at = datetime('now','localtime')
        WHERE id = ?
    ");
    $stmt->execute([
        $data['site_title'] ?? null,
        $data['video_title'] ?? null,
        $data['duration_seconds'] ?? null,
        $data['duration_display'] ?? null,
        $data['extractor_used'] ?? null,
        $id,
    ]);
}

/**
 * Mark a job as failed.
 */
function qm_mark_failed(int $id, string $error): void {
    $db = qm_get_db();
    $stmt = $db->prepare("
        UPDATE jobs SET
            status = 'failed',
            error_message = ?,
            completed_at = datetime('now','localtime')
        WHERE id = ?
    ");
    $stmt->execute([$error, $id]);
}

/**
 * Get counts by status.
 */
function qm_get_counts(): array {
    $db = qm_get_db();
    $rows = $db->query("SELECT status, COUNT(*) as cnt FROM jobs GROUP BY status")->fetchAll();
    $counts = ['queued' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0, 'total' => 0];
    foreach ($rows as $r) {
        $counts[$r['status']] = (int) $r['cnt'];
        $counts['total'] += (int) $r['cnt'];
    }
    return $counts;
}

/**
 * Get jobs by status.
 */
function qm_get_jobs(?string $status = null, int $limit = 50, int $offset = 0): array {
    $db = qm_get_db();
    if ($status) {
        $stmt = $db->prepare("SELECT * FROM jobs WHERE status=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$status, $limit, $offset]);
    } else {
        $stmt = $db->prepare("SELECT * FROM jobs ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
    }
    return $stmt->fetchAll();
}

/**
 * Get a single job by ID.
 */
function qm_get_job(int $id): ?array {
    $db = qm_get_db();
    $stmt = $db->prepare("SELECT * FROM jobs WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Get the next queued job (FIFO).
 */
function qm_next_queued(): ?array {
    $db = qm_get_db();
    return $db->query("SELECT * FROM jobs WHERE status='queued' ORDER BY id ASC LIMIT 1")->fetch() ?: null;
}

/**
 * Count currently processing jobs.
 */
function qm_processing_count(): int {
    $db = qm_get_db();
    return (int) $db->query("SELECT COUNT(*) FROM jobs WHERE status='processing'")->fetchColumn();
}

/**
 * Convert seconds to human-readable pirate time.
 */
function qm_format_duration(int $seconds): string {
    if ($seconds < 0) return 'Unknown';
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;

    $parts = [];
    if ($h > 0) $parts[] = "{$h}h";
    if ($m > 0) $parts[] = "{$m}m";
    $parts[] = "{$s}s";

    return implode(' ', $parts);
}
