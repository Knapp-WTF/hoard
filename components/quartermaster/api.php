<?php
/**
 * 🗺️ QUARTERMASTER - API Endpoint
 *
 * Handles AJAX requests from the dashboard.
 *
 * Actions:
 *   POST ?action=submit    → Submit a URL to the queue
 *   GET  ?action=status    → Get queue counts
 *   GET  ?action=queue     → Get current queue items
 *   GET  ?action=process   → Process next queued job
 *   GET  ?action=completed → Get completed jobs (paginated)
 *   GET  ?action=failed    → Get failed jobs
 *   GET  ?action=job&id=N  → Get single job details
 */

require_once __DIR__ . '/database.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'submit':
        $url = trim($_POST['url'] ?? '');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['error' => "That be no valid address, Captain!"]);
            exit;
        }

        $id = qm_enqueue($url);
        echo json_encode(['success' => true, 'job_id' => $id, 'message' => "Address logged! Job #{$id} added to the queue."]);
        break;

    case 'status':
        echo json_encode(qm_get_counts());
        break;

    case 'queue':
        $jobs = qm_get_jobs('queued', 20);
        $processing = qm_get_jobs('processing', 10);
        echo json_encode(['queued' => $jobs, 'processing' => $processing]);
        break;

    case 'process':
        // Include the processor
        // Re-use its logic but we already have the headers set
        require_once __DIR__ . '/extractors/base.php';

        $processing = qm_processing_count();
        if ($processing >= QM_MAX_CONCURRENT) {
            echo json_encode(['action' => 'wait']);
            exit;
        }

        $job = qm_next_queued();
        if (!$job) {
            echo json_encode(['action' => 'idle']);
            exit;
        }

        $id = (int) $job['id'];
        qm_mark_processing($id);
        hoard_log("Quartermaster: Processing job #$id — {$job['url']}");

        try {
            $extractor = qm_get_extractor($job['url']);
            $result = $extractor->extract();
            $result['duration_display'] = qm_format_duration($result['duration_seconds']);
            $result['extractor_used'] = $extractor->name();
            qm_mark_completed($id, $result);
            hoard_log("Quartermaster: Job #$id completed — {$result['duration_display']}");
            echo json_encode(['action' => 'completed', 'job_id' => $id, 'result' => $result]);
        } catch (Exception $e) {
            qm_mark_failed($id, $e->getMessage());
            hoard_log("Quartermaster: Job #$id failed — " . $e->getMessage(), 'ERROR');
            echo json_encode(['action' => 'failed', 'job_id' => $id, 'error' => $e->getMessage()]);
        }
        break;

    case 'completed':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = QM_PER_PAGE;
        $offset = ($page - 1) * $limit;
        $jobs = qm_get_jobs('completed', $limit, $offset);
        $counts = qm_get_counts();
        echo json_encode([
            'jobs'  => $jobs,
            'total' => $counts['completed'],
            'page'  => $page,
            'pages' => max(1, ceil($counts['completed'] / $limit)),
        ]);
        break;

    case 'failed':
        echo json_encode(qm_get_jobs('failed', 50));
        break;

    case 'job':
        $id = (int)($_GET['id'] ?? 0);
        $job = qm_get_job($id);
        if (!$job) {
            http_response_code(404);
            echo json_encode(['error' => 'Job not found']);
            exit;
        }
        echo json_encode($job);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
