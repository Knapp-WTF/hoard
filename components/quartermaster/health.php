<?php
/**
 * 🗺️ QUARTERMASTER - Health Check
 */

require_once __DIR__ . '/database.php';

$counts = qm_get_counts();
$ytdlp_ok = file_exists(QM_YTDLP_BIN) && is_executable(QM_YTDLP_BIN);

$status = $ytdlp_ok ? 'active' : 'error';
$message = $ytdlp_ok
    ? "Operational — {$counts['completed']} completed, {$counts['queued']} queued"
    : "yt-dlp not found at " . QM_YTDLP_BIN;

return [
    'status'  => $status,
    'message' => $message,
    'counts'  => $counts,
    'tools'   => ['yt-dlp' => $ytdlp_ok],
];
