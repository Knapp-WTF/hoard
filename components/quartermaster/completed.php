<?php
/**
 * 🗺️ QUARTERMASTER - Full Plunder Log
 *
 * Complete list of all inspected videos with addresses, titles, and durations.
 */

require_once __DIR__ . '/database.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = QM_PER_PAGE;
$offset = ($page - 1) * $limit;

$jobs = qm_get_jobs('completed', $limit, $offset);
$counts = qm_get_counts();
$total_pages = max(1, ceil($counts['completed'] / $limit));

// Calculate total duration of all completed
$db = qm_get_db();
$total_duration = (int) $db->query("SELECT COALESCE(SUM(duration_seconds), 0) FROM jobs WHERE status='completed'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= HOARD_SITE_TITLE ?> — Plunder Log</title>
    <link rel="stylesheet" href="../../assets/css/hoard.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📜</text></svg>">
    <style>
        .plunder-table {
            width: 100%;
            border-collapse: collapse;
        }
        .plunder-table th {
            text-align: left;
            padding: 0.8rem 1rem;
            color: var(--gold);
            font-family: 'Cinzel', serif;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--border);
            position: sticky;
            top: 60px;
            background: var(--bg-deck);
            z-index: 10;
        }
        .plunder-table td {
            padding: 0.7rem 1rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border);
        }
        .plunder-table tr:hover td {
            background: var(--bg-cabin);
        }
        .plunder-table .title-cell {
            color: var(--text-primary);
            max-width: 350px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .plunder-table .duration-cell {
            font-family: 'Cinzel', serif;
            color: var(--gold);
            font-weight: 600;
            white-space: nowrap;
        }
        .plunder-table .url-cell a {
            color: var(--storm-blue);
            text-decoration: none;
            max-width: 250px;
            display: inline-block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: bottom;
        }
        .plunder-table .url-cell a:hover { color: var(--gold); }
        .plunder-table .source-cell {
            color: var(--text-muted);
        }
        .plunder-table .date-cell {
            color: var(--text-muted);
            font-size: 0.8rem;
            white-space: nowrap;
        }
        .plunder-table .job-id {
            color: var(--text-muted);
            font-size: 0.75rem;
            font-family: monospace;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-size: 0.85rem;
            text-decoration: none;
            font-family: 'Cinzel', serif;
        }
        .pagination a {
            background: var(--bg-cabin);
            color: var(--gold);
            border: 1px solid var(--border);
        }
        .pagination a:hover {
            border-color: var(--gold-dim);
        }
        .pagination .current {
            background: var(--gold-dim);
            color: var(--bg-abyss);
            border: 1px solid var(--gold);
        }

        .summary-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .summary-stat {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .summary-stat strong {
            color: var(--gold);
            font-family: 'Cinzel', serif;
        }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="/" class="nav-brand">
        <span>🏴‍☠️</span> THE HOARD
    </a>
    <div class="nav-status">
        <a href="./" style="color: var(--text-secondary); text-decoration: none; font-size: 0.85rem;">← Quartermaster</a>
        <a href="/" style="color: var(--text-muted); text-decoration: none; font-size: 0.85rem;">Command Deck</a>
    </div>
</nav>

<main class="dashboard">

    <div class="dashboard-header">
        <h1>📜 The Plunder Log</h1>
        <p>Every video inspected by the Quartermaster, with full reckonin's.</p>
    </div>

    <div class="summary-bar">
        <div class="summary-stat">
            <strong><?= $counts['completed'] ?></strong> videos plundered
        </div>
        <div class="summary-stat">
            Total runtime: <strong><?= qm_format_duration($total_duration) ?></strong>
        </div>
        <div class="summary-stat">
            Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
        </div>
    </div>

    <?php if (empty($jobs)): ?>
    <div class="empty-fleet">
        <div class="icon">📜</div>
        <p>The log be empty, Captain. No videos plundered yet.</p>
    </div>
    <?php else: ?>
    <div class="system-panel" style="padding: 0; overflow-x: auto;">
        <table class="plunder-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Duration</th>
                    <th>Address</th>
                    <th>Source</th>
                    <th>Plundered</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td class="job-id">#<?= $job['id'] ?></td>
                    <td class="title-cell" title="<?= htmlspecialchars($job['video_title'] ?? '') ?>">
                        <?= htmlspecialchars($job['video_title'] ?? 'Unknown Vessel') ?>
                    </td>
                    <td class="duration-cell"><?= htmlspecialchars($job['duration_display'] ?? '—') ?></td>
                    <td class="url-cell">
                        <a href="<?= htmlspecialchars($job['url']) ?>" target="_blank" title="<?= htmlspecialchars($job['url']) ?>">
                            <?= htmlspecialchars($job['url']) ?>
                        </a>
                    </td>
                    <td class="source-cell"><?= htmlspecialchars($job['site_title'] ?? $job['domain']) ?></td>
                    <td class="date-cell"><?= $job['completed_at'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>">← Prev</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 3);
        $end = min($total_pages, $page + 3);
        for ($p = $start; $p <= $end; $p++):
        ?>
            <?php if ($p === $page): ?>
                <span class="current"><?= $p ?></span>
            <?php else: ?>
                <a href="?page=<?= $p ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</main>

<footer class="footer">
    ⚓ The Hoard · Quartermaster Plunder Log
</footer>

</body>
</html>
