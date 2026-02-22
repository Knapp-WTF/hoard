<?php
/**
 * 🗺️ QUARTERMASTER - Dashboard
 *
 * Submit URLs, watch the queue, see results.
 */

require_once __DIR__ . '/database.php';

$counts = qm_get_counts();
$queue = qm_get_jobs('queued', 20);
$processing = qm_get_jobs('processing', 10);
$recent_completed = qm_get_jobs('completed', 5);
$recent_failed = qm_get_jobs('failed', 5);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= HOARD_SITE_TITLE ?> — Quartermaster</title>
    <link rel="stylesheet" href="../../assets/css/hoard.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🗺️</text></svg>">
    <style>
        .qm-submit-form {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 2rem;
        }
        .qm-submit-form input[type="url"] {
            flex: 1;
            background: var(--bg-cabin);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.8rem 1rem;
            color: var(--text-primary);
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: var(--transition);
        }
        .qm-submit-form input[type="url"]:focus {
            border-color: var(--gold-dim);
            box-shadow: 0 0 0 3px var(--gold-glow);
        }
        .qm-submit-form input[type="url"]::placeholder {
            color: var(--text-muted);
        }
        .btn-plunder {
            background: linear-gradient(135deg, var(--gold-dim), var(--gold));
            color: var(--bg-abyss);
            border: none;
            border-radius: var(--radius);
            padding: 0.8rem 1.8rem;
            font-family: 'Cinzel', serif;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }
        .btn-plunder:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(212, 168, 67, 0.3);
        }
        .btn-plunder:active { transform: translateY(0); }
        .btn-plunder:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--bg-cabin);
            border: 1px solid var(--gold-dim);
            border-radius: var(--radius);
            padding: 1rem 1.5rem;
            color: var(--gold);
            font-size: 0.9rem;
            box-shadow: var(--shadow);
            z-index: 1000;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast.error { border-color: var(--blood-red); color: var(--blood-red); }

        /* Queue Table */
        .queue-table {
            width: 100%;
            border-collapse: collapse;
        }
        .queue-table th {
            text-align: left;
            padding: 0.6rem 0.8rem;
            color: var(--gold);
            font-family: 'Cinzel', serif;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--border);
        }
        .queue-table td {
            padding: 0.6rem 0.8rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border);
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .queue-table tr:last-child td { border-bottom: none; }
        .queue-table .url-cell a {
            color: var(--storm-blue);
            text-decoration: none;
        }
        .queue-table .url-cell a:hover { color: var(--gold); }
        .queue-table .duration-cell {
            font-family: 'Cinzel', serif;
            color: var(--gold);
            font-weight: 600;
        }

        /* Processing animation */
        .processing-spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid var(--border-light);
            border-top-color: var(--gold);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 0.4rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .view-all-link {
            display: inline-block;
            margin-top: 1rem;
            color: var(--gold);
            text-decoration: none;
            font-size: 0.85rem;
            font-family: 'Cinzel', serif;
        }
        .view-all-link:hover { color: var(--gold-bright); }

        .section-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 900px) {
            .section-split { grid-template-columns: 1fr; }
            .qm-submit-form { flex-direction: column; }
        }
    </style>
</head>
<body>

<nav class="nav-bar">
    <a href="/" class="nav-brand">
        <span>🏴‍☠️</span> THE HOARD
    </a>
    <div class="nav-status">
        <a href="/" style="color: var(--text-secondary); text-decoration: none; font-size: 0.85rem;">← Command Deck</a>
        <div class="nav-clock" id="ship-clock">--:--:--</div>
    </div>
</nav>

<main class="dashboard">

    <!-- Header -->
    <div class="pirate-banner" style="padding: 2rem;">
        <div class="skull">🗺️</div>
        <h2>The Quartermaster</h2>
        <p>"Every voyage has a length — I measure 'em all."</p>
    </div>

    <!-- Submit Form -->
    <form class="qm-submit-form" id="submit-form">
        <input type="url" id="url-input" placeholder="Paste a web address containin' a video, Captain..." required>
        <button type="submit" class="btn-plunder" id="submit-btn">⚓ Plunder It</button>
    </form>

    <!-- Stats Row -->
    <div class="stats-row" id="stats-row">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-value" id="stat-queued"><?= $counts['queued'] ?></div>
            <div class="stat-label">In Queue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-value" id="stat-processing"><?= $counts['processing'] ?></div>
            <div class="stat-label">Processing</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value" id="stat-completed"><?= $counts['completed'] ?></div>
            <div class="stat-label">Plundered</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💀</div>
            <div class="stat-value" id="stat-failed"><?= $counts['failed'] ?></div>
            <div class="stat-label">Sunk</div>
        </div>
    </div>

    <!-- Queue + Processing -->
    <div class="section-split">
        <!-- Active Queue -->
        <div class="system-panel">
            <h2>📋 The Queue</h2>
            <div id="queue-list">
                <?php if (empty($queue) && empty($processing)): ?>
                <p style="color: var(--text-muted); font-size: 0.85rem;">No addresses awaitin' inspection. Submit one above!</p>
                <?php else: ?>
                <table class="queue-table">
                    <thead><tr><th>Status</th><th>Address</th><th>Submitted</th></tr></thead>
                    <tbody>
                    <?php foreach ($processing as $job): ?>
                    <tr>
                        <td><span class="processing-spinner"></span> Plundering...</td>
                        <td class="url-cell"><a href="<?= htmlspecialchars($job['url']) ?>" target="_blank"><?= htmlspecialchars($job['domain']) ?></a></td>
                        <td><?= $job['created_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($queue as $job): ?>
                    <tr>
                        <td><span class="status-badge status-planned">Queued</span></td>
                        <td class="url-cell"><a href="<?= htmlspecialchars($job['url']) ?>" target="_blank"><?= htmlspecialchars($job['domain']) ?></a></td>
                        <td><?= $job['created_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Completed -->
        <div class="system-panel">
            <h2>✅ Recently Plundered</h2>
            <div id="completed-list">
                <?php if (empty($recent_completed)): ?>
                <p style="color: var(--text-muted); font-size: 0.85rem;">No plunder yet. The seas await!</p>
                <?php else: ?>
                <table class="queue-table">
                    <thead><tr><th>Title</th><th>Duration</th><th>Source</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_completed as $job): ?>
                    <tr>
                        <td title="<?= htmlspecialchars($job['video_title'] ?? '') ?>"><?= htmlspecialchars(mb_strimwidth($job['video_title'] ?? 'Unknown', 0, 40, '...')) ?></td>
                        <td class="duration-cell"><?= htmlspecialchars($job['duration_display'] ?? '?') ?></td>
                        <td><?= htmlspecialchars($job['site_title'] ?? $job['domain']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <a href="completed.php" class="view-all-link">📜 View Full Plunder Log →</a>
        </div>
    </div>

    <!-- Recent Failures -->
    <?php if (!empty($recent_failed)): ?>
    <div class="system-panel">
        <h2>💀 Recent Shipwrecks</h2>
        <table class="queue-table">
            <thead><tr><th>Address</th><th>Error</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($recent_failed as $job): ?>
            <tr>
                <td class="url-cell"><a href="<?= htmlspecialchars($job['url']) ?>" target="_blank"><?= htmlspecialchars($job['domain']) ?></a></td>
                <td style="color: var(--blood-red);"><?= htmlspecialchars(mb_strimwidth($job['error_message'] ?? '?', 0, 60, '...')) ?></td>
                <td><?= $job['completed_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</main>

<footer class="footer">
    ⚓ The Hoard · Quartermaster v1.0.0
</footer>

<div class="toast" id="toast"></div>

<script>
const API = 'api.php';
let processing = false;

// Submit form
document.getElementById('submit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('url-input');
    const btn = document.getElementById('submit-btn');
    const url = input.value.trim();
    if (!url) return;

    btn.disabled = true;
    btn.textContent = '⏳ Loggin\'...';

    try {
        const fd = new FormData();
        fd.append('action', 'submit');
        fd.append('url', url);

        const resp = await fetch(API, { method: 'POST', body: fd });
        const data = await resp.json();

        if (data.error) {
            showToast(data.error, true);
        } else {
            showToast(`⚓ ${data.message}`);
            input.value = '';
            refreshStatus();
            processQueue();
        }
    } catch (err) {
        showToast('Failed to submit — ' + err.message, true);
    }

    btn.disabled = false;
    btn.textContent = '⚓ Plunder It';
});

// Process queue items
async function processQueue() {
    if (processing) return;
    processing = true;

    try {
        const resp = await fetch(`${API}?action=process`);
        const data = await resp.json();

        if (data.action === 'completed' || data.action === 'failed') {
            refreshStatus();
            // Check if more in queue
            const statusResp = await fetch(`${API}?action=status`);
            const status = await statusResp.json();
            if (status.queued > 0) {
                processing = false;
                processQueue();
                return;
            }
        }

        if (data.action === 'completed') {
            showToast(`✅ Plundered! ${data.result.duration_display} — ${data.result.video_title || 'Unknown'}`);
        } else if (data.action === 'failed') {
            showToast(`💀 Failed: ${data.error}`, true);
        }
    } catch (err) {
        console.error('Process error:', err);
    }

    processing = false;
}

// Refresh status counts
async function refreshStatus() {
    try {
        const resp = await fetch(`${API}?action=status`);
        const data = await resp.json();
        document.getElementById('stat-queued').textContent = data.queued;
        document.getElementById('stat-processing').textContent = data.processing;
        document.getElementById('stat-completed').textContent = data.completed;
        document.getElementById('stat-failed').textContent = data.failed;
    } catch (err) {}

    // Refresh page sections after a delay
    setTimeout(() => location.reload(), 2000);
}

// Toast notification
function showToast(msg, isError = false) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast show' + (isError ? ' error' : '');
    setTimeout(() => el.className = 'toast', 4000);
}

// Ship's clock
function initClock() {
    const el = document.getElementById('ship-clock');
    setInterval(() => {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-US', { hour12: false });
    }, 1000);
}

initClock();

// Auto-process any queued items on load
<?php if ($counts['queued'] > 0): ?>
processQueue();
<?php endif; ?>
</script>
</body>
</html>
