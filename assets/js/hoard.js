/**
 * ⚓ HOARD - Dashboard Scripts
 */

document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initUptime();
    initComponentChecks();
});

// ── Ship's Clock ──
function initClock() {
    const el = document.getElementById('ship-clock');
    if (!el) return;
    
    function update() {
        const now = new Date();
        const h = now.getHours();
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        
        // Convert to ship's bells (nautical time flavor)
        const watch = h < 4 ? 'Middle Watch' :
                      h < 8 ? 'Morning Watch' :
                      h < 12 ? 'Forenoon Watch' :
                      h < 16 ? 'Afternoon Watch' :
                      h < 20 ? 'Dog Watch' : 'First Watch';
        
        el.textContent = `${String(h).padStart(2, '0')}:${m}:${s} · ${watch}`;
    }
    
    update();
    setInterval(update, 1000);
}

// ── Uptime Counter ──
function initUptime() {
    const el = document.getElementById('uptime');
    if (!el) return;
    
    const start = parseInt(el.dataset.start || (Date.now() / 1000));
    
    function update() {
        const elapsed = Math.floor(Date.now() / 1000) - start;
        const days = Math.floor(elapsed / 86400);
        const hours = Math.floor((elapsed % 86400) / 3600);
        const mins = Math.floor((elapsed % 3600) / 60);
        
        let parts = [];
        if (days > 0) parts.push(`${days}d`);
        parts.push(`${hours}h`);
        parts.push(`${mins}m`);
        
        el.textContent = parts.join(' ');
    }
    
    update();
    setInterval(update, 60000);
}

// ── Component Health Checks ──
function initComponentChecks() {
    document.querySelectorAll('[data-health-url]').forEach(card => {
        const url = card.dataset.healthUrl;
        const badge = card.querySelector('.status-badge');
        
        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.status === 'active') {
                    badge.className = 'status-badge status-active';
                    badge.innerHTML = '<span class="pulse-dot"></span> Active';
                } else if (data.status === 'error') {
                    badge.className = 'status-badge status-error';
                    badge.textContent = '⚠ Error';
                }
            })
            .catch(() => {
                // Leave as-is if health check fails
            });
    });
}

// ── Auto-refresh dashboard every 60s ──
setTimeout(() => location.reload(), 60000);
