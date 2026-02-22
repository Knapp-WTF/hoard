<?php
/**
 * 🗺️ QUARTERMASTER - Queue Processor
 *
 * Picks up the next queued job and processes it.
 * Called via AJAX from the dashboard or via CLI/cron.
 *
 * Usage:
 *   CLI:  php process.php
 *   HTTP: GET /components/quartermaster/process.php (returns JSON)
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/extractors/base.php';

header('Content-Type: application/json');

// Check if we're at max capacity
$processing = qm_processing_count();
if ($processing >= QM_MAX_CONCURRENT) {
    echo json_encode(['action' => 'wait', 'message' => 'All hands on deck — max concurrent reached']);
    exit;
}

// Grab next job
$job = qm_next_queued();
if (!$job) {
    echo json_encode(['action' => 'idle', 'message' => 'No jobs in the queue, Captain']);
    exit;
}

$id = (int) $job['id'];
qm_mark_processing($id);

hoard_log("Quartermaster: Processing job #$id — {$job['url']}");

try {
    $extractor = qm_get_extractor($job['url']);
    $result = $extractor->extract();

    // Add formatted duration
    $result['duration_display'] = qm_format_duration($result['duration_seconds']);
    $result['extractor_used'] = $extractor->name();

    qm_mark_completed($id, $result);

    hoard_log("Quartermaster: Job #$id completed — {$result['duration_display']} ({$result['video_title']})");

    echo json_encode([
        'action'  => 'completed',
        'job_id'  => $id,
        'result'  => $result,
    ]);

} catch (Exception $e) {
    $error = $e->getMessage();
    qm_mark_failed($id, $error);

    hoard_log("Quartermaster: Job #$id failed — $error", 'ERROR');

    echo json_encode([
        'action'  => 'failed',
        'job_id'  => $id,
        'error'   => $error,
    ]);
}
