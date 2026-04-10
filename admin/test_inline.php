<?php
/**
 * Complete Inline Test - Safe Version
 */

$apiBase = '/api';
$lang = 'en';
$dir = 'ltr';
$csrf = bin2hex(random_bytes(16));
$tenantId = 1;
$userId = 1;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Loader - Safe Inline Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .color-circle { width: 25px; height: 25px; border-radius: 50%; display: inline-block; margin-right: 5px; border: 2px solid #dee2e6; }
        .console-output { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
        .log-info { color: #4fc3f7; }
        .log-success { color: #66bb6a; }
        .log-error { color: #ef5350; }
        .log-warning { color: #ffb74d; }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-8">
            <h1 class="mb-4">Theme Loader - Safe Inline Test</h1>

            <div class="alert alert-info">
                <h5>Test Info</h5>
                <ul class="mb-0">
                    <li>API Endpoint: <code><?= $apiBase ?>/themes</code></li>
                    <li>All JS is inline – no external files</li>
                    <li>Safe from caching or missing element errors</li>
                </ul>
            </div>

            <div id="status" class="alert alert-secondary">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Initializing...
            </div>

            <div id="results"></div>
        </div>

        <div class="col-md-4">
            <h5>Console Output</h5>
            <div id="console" class="console-output"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const APP_CONFIG = {
    API_BASE: '<?= $apiBase ?>',
    TENANT_ID: <?= $tenantId ?>,
    CSRF_TOKEN: '<?= $csrf ?>',
    USER_ID: <?= $userId ?>
};

const consoleEl = document.getElementById('console');
const statusEl = document.getElementById('status');
const resultsEl = document.getElementById('results');

function log(message, type='info') {
    const timestamp = new Date().toLocaleTimeString();
    const span = document.createElement('span');
    span.className = `log-${type}`;
    span.textContent = `[${timestamp}] ${message}\n`;
    consoleEl.appendChild(span);
    consoleEl.scrollTop = consoleEl.scrollHeight;
    console[type==='error' ? 'error' : 'log'](message);
}

function updateStatus(message, type='secondary') {
    statusEl.className = `alert alert-${type}`;
    statusEl.innerHTML = message;
}

async function loadThemes() {
    log('=== THEME LOADER TEST STARTED ===');
    log(`API Base: ${APP_CONFIG.API_BASE}`);
    log(`Tenant ID: ${APP_CONFIG.TENANT_ID}`);

    try {
        const url = `${APP_CONFIG.API_BASE}/themes`;
        log(`Fetching: ${url}`);
        updateStatus('<span class="spinner-border spinner-border-sm me-2"></span> Loading themes...', 'info');

        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-Token': APP_CONFIG.CSRF_TOKEN }
        });

        const data = await response.json();
        log(`HTTP ${response.status}: ${response.statusText}`, response.ok ? 'success' : 'error');
        log(`Data keys: ${JSON.stringify(Object.keys(data))}`);

        if (!response.ok) throw new Error(`HTTP ${response.status}: ${data.message || response.statusText}`);

        const themes = data.data || data.themes || [];
        log(`Themes found: ${themes.length}`);

        if (themes.length === 0) {
            updateStatus('No themes found in database', 'warning');
            resultsEl.innerHTML = `<div class="alert alert-warning"><h5>No Themes Found</h5><pre>${JSON.stringify(data, null, 2)}</pre></div>`;
        } else {
            displayThemes(themes);
            updateStatus(`Successfully loaded ${themes.length} theme(s)`, 'success');
        }

    } catch (err) {
        log(`ERROR: ${err.message}`, 'error');
        log(err.stack || '', 'error');
        updateStatus(`<i class="fas fa-exclamation-circle"></i> Error: ${err.message}`, 'danger');
        resultsEl.innerHTML = `<div class="alert alert-danger"><h5>Error Loading Themes</h5><pre>${err.message}\n${err.stack}</pre></div>`;
    }
}

function displayThemes(themes) {
    log(`Rendering ${themes.length} themes`);
    let html = `<table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th><th>Name</th><th>Author</th><th>Version</th><th>Status</th><th>Colors</th><th>Created</th>
                        </tr>
                    </thead><tbody>`;
    themes.forEach(theme => {
        const isActive = theme.is_active == 1 || theme.is_active === true;
        html += `<tr>
                    <td>${theme.id || 'N/A'}</td>
                    <td><strong>${escapeHtml(theme.name || 'Unnamed')}</strong>${theme.is_default ? ' <span class="badge bg-info ms-2">Default</span>' : ''}</td>
                    <td>${escapeHtml(theme.author || 'Unknown')}</td>
                    <td><code>${theme.version || '1.0.0'}</code></td>
                    <td><span class="badge ${isActive ? 'bg-success' : 'bg-secondary'}">${isActive ? 'Active' : 'Inactive'}</span></td>
                    <td>
                        <span class="color-circle" style="background-color:${theme.primary_color || '#3b82f6'}" title="Primary: ${theme.primary_color || '#3b82f6'}"></span>
                        <span class="color-circle" style="background-color:${theme.secondary_color || '#64748b'}" title="Secondary: ${theme.secondary_color || '#64748b'}"></span>
                    </td>
                    <td><small>${theme.created_at || 'N/A'}</small></td>
                </tr>`;
    });
    html += `</tbody></table>`;
    resultsEl.innerHTML = html;
    log('Rendering complete', 'success');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

window.addEventListener('load', () => {
    log('Page loaded');
    loadThemes();
});
</script>
</body>
</html>
