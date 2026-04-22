<?php
declare(strict_types=1);

/**
 * admin/platform_login.php
 *
 * Dedicated Platform Admin login page.
 * Only users listed in the `platform_users` table may authenticate here.
 * On success, redirects to admin/dashboard.php
 */

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Use the same session save path as the API bootstrap (api/shared/config/session.php)
    // so that the CSRF token stored here is visible to api/v1/routes/platform_auth.php.
    $apiSessionPath = dirname(__DIR__) . '/api/storage/sessions';
    if (!is_dir($apiSessionPath)) {
        @mkdir($apiSessionPath, 0700, true);
    }
    if (is_dir($apiSessionPath)) {
        ini_set('session.save_path', $apiSessionPath);
    }

    session_name('APP_SESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Already authenticated → redirect straight to dashboard
if (!empty($_SESSION['platform_admin'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Platform Admin — Secure Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & Base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-deep:      #060910;
            --bg-card:      #0d1117;
            --bg-input:     #080c12;
            --border-subtle:#1c2333;
            --border-focus: #3b82f6;
            --accent:       #3b82f6;
            --accent-glow:  rgba(59, 130, 246, 0.18);
            --accent-hover: #2563eb;
            --text-primary: #f0f4ff;
            --text-secondary:#8b96b0;
            --text-muted:   #4a546a;
            --badge-bg:     rgba(59, 130, 246, 0.08);
            --badge-border: rgba(59, 130, 246, 0.25);
            --badge-text:   #93c5fd;
            --success-bg:   rgba(16, 185, 129, 0.08);
            --success-border:rgba(16, 185, 129, 0.3);
            --success-text: #6ee7b7;
            --error-bg:     rgba(239, 68, 68, 0.08);
            --error-border: rgba(239, 68, 68, 0.3);
            --error-text:   #fca5a5;
            --field-err:    #f87171;
            --radius-sm:    6px;
            --radius-md:    10px;
            --radius-lg:    16px;
            --shadow-card:  0 32px 80px rgba(0,0,0,0.7), 0 0 0 1px var(--border-subtle);
            --transition:   0.18s cubic-bezier(0.4,0,0.2,1);
        }

        html, body {
            height: 100%;
            font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
            background: var(--bg-deep);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ── Background grid + glow ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(59,130,246,0.12) 0%, transparent 70%),
                linear-gradient(rgba(255,255,255,0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.015) 1px, transparent 1px);
            background-size: auto, 40px 40px, 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Layout ── */
        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        /* ── Card ── */
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            padding: 2.5rem 2.25rem 2rem;
            animation: slideUp 0.45s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Header ── */
        .header {
            text-align: center;
            margin-bottom: 2.25rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--badge-bg);
            border: 1px solid var(--badge-border);
            color: var(--badge-text);
            font-family: 'DM Mono', monospace;
            font-size: 0.6875rem;
            font-weight: 500;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 0.3rem 0.85rem;
            border-radius: 99px;
            margin-bottom: 1.25rem;
        }

        .badge svg {
            flex-shrink: 0;
        }

        .header h1 {
            font-size: 1.625rem;
            font-weight: 600;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            line-height: 1.2;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 300;
            line-height: 1.5;
        }

        /* ── Divider ── */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border-subtle), transparent);
            margin-bottom: 2rem;
        }

        /* ── Form ── */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            letter-spacing: 0.01em;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            transition: color var(--transition);
            display: flex;
        }

        /* ── THE KEY FIX: explicit user-select + pointer-events on inputs ── */
        .input-wrap input {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9375rem;
            font-weight: 400;
            padding: 0.7rem 2.75rem 0.7rem 2.75rem;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
            -webkit-user-select: text;
            user-select: text;
            pointer-events: auto;
            cursor: text;
        }

        .input-wrap input::placeholder {
            color: var(--text-muted);
            font-weight: 300;
        }

        .input-wrap input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .input-wrap input:focus ~ .input-icon,
        .input-wrap:focus-within .input-icon {
            color: var(--accent);
        }

        /* ── Password toggle ── */
        .toggle-pw {
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            padding: 0;
            display: flex;
            align-items: center;
            transition: color var(--transition);
            line-height: 1;
        }

        .toggle-pw:hover {
            color: var(--text-secondary);
        }

        /* ── Field error ── */
        .field-err {
            font-size: 0.78rem;
            color: var(--field-err);
            margin-top: 0.375rem;
            display: none;
            align-items: center;
            gap: 4px;
        }

        .field-err.visible {
            display: flex;
        }

        /* ── Submit button ── */
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9375rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            padding: 0.78rem 1rem;
            cursor: pointer;
            transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
            margin-top: 0.75rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, transparent 60%);
            pointer-events: none;
        }

        .btn:hover:not(:disabled) {
            background: var(--accent-hover);
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.35);
        }

        .btn:active:not(:disabled) {
            transform: scale(0.985);
        }

        .btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        /* ── Spinner ── */
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }

        .btn.loading .spinner { display: block; }
        .btn.loading .btn-text { opacity: 0.8; }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ── Result messages ── */
        .result {
            margin-top: 1.25rem;
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            display: none;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.45;
        }

        .result.visible { display: flex; }

        .result.ok {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .result.err {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        .result svg { flex-shrink: 0; margin-top: 1px; }

        /* ── Footer ── */
        .footer {
            margin-top: 1.75rem;
            text-align: center;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8125rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: color var(--transition);
        }

        .back-link:hover { color: var(--text-secondary); }

        /* ── Security note ── */
        .sec-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-top: 2rem;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-family: 'DM Mono', monospace;
            letter-spacing: 0.04em;
        }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            .card { padding: 2rem 1.5rem 1.5rem; }
            .header h1 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">

        <!-- Header -->
        <div class="header">
            <div class="badge">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Platform Admin
            </div>
            <h1>Secure Sign In</h1>
            <p>Access restricted to authorised platform administrators</p>
        </div>

        <div class="divider"></div>

        <!-- Login Form -->
        <!-- NOTE: autocomplete="off" removed from <form> — it blocks password managers & input on some browsers -->
        <form id="loginForm" novalidate>
            <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo $csrf; ?>">

            <!-- Identifier -->
            <div class="form-group">
                <label for="identifier">
                    Username or Email
                </label>
                <div class="input-wrap">
                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    <input
                        type="text"
                        id="identifier"
                        name="identifier"
                        placeholder="Enter username or email"
                        autocomplete="username"
                        spellcheck="false"
                        autocorrect="off"
                        autocapitalize="off"
                    >
                </div>
                <div class="field-err" id="err-identifier">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span></span>
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <!-- type="password" explicitly set; NOT readonly, NOT disabled -->
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                    >
                    <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password visibility" tabindex="-1">
                        <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <div class="field-err" id="err-password">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span></span>
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn" id="submitBtn">
                <div class="spinner"></div>
                <span class="btn-text">Sign In</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" id="btnArrow">
                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>
        </form>

        <!-- Result banner -->
        <div class="result" id="result" role="status" aria-live="polite"></div>

        <!-- Footer -->
        <div class="footer">
            <a href="/admin/login.php" class="back-link">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                </svg>
                Standard Admin Login
            </a>
        </div>
    </div>

    <!-- Security indicator -->
    <div class="sec-note">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        TLS · CSRF protected · Rate limited
    </div>
</div>

<script>
(function () {
    'use strict';

    var form       = document.getElementById('loginForm');
    var submitBtn  = document.getElementById('submitBtn');
    var resultEl   = document.getElementById('result');
    var togglePw   = document.getElementById('togglePw');
    var pwInput    = document.getElementById('password');
    var eyeIcon    = document.getElementById('eyeIcon');

    // ── Password visibility toggle ─────────────────────────────────────────
    var pwVisible = false;
    togglePw.addEventListener('click', function () {
        pwVisible = !pwVisible;
        pwInput.type = pwVisible ? 'text' : 'password';
        eyeIcon.innerHTML = pwVisible
            ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>' +
              '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>' +
              '<line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        pwInput.focus();
    });

    // ── Helpers ───────────────────────────────────────────────────────────
    function escHtml(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function clearFieldErr(field) {
        var el = document.getElementById('err-' + field);
        if (el) { el.classList.remove('visible'); el.querySelector('span').textContent = ''; }
    }

    function showFieldErr(field, msg) {
        var el = document.getElementById('err-' + field);
        if (el) { el.classList.add('visible'); el.querySelector('span').textContent = msg; }
        var input = document.getElementById(field);
        if (input) { input.style.borderColor = 'var(--field-err)'; }
    }

    function clearAllErrors() {
        ['identifier','password'].forEach(function(f){
            clearFieldErr(f);
            var inp = document.getElementById(f);
            if (inp) inp.style.borderColor = '';
        });
    }

    function showResult(msg, ok) {
        var icon = ok
            ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
            : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        resultEl.innerHTML = icon + '<span>' + escHtml(msg) + '</span>';
        resultEl.className = 'result visible ' + (ok ? 'ok' : 'err');
    }

    function clearResult() {
        resultEl.className = 'result';
        resultEl.innerHTML = '';
    }

    function setLoading(on) {
        submitBtn.disabled = on;
        submitBtn.classList.toggle('loading', on);
        document.getElementById('btnArrow').style.display = on ? 'none' : '';
        submitBtn.querySelector('.btn-text').textContent = on ? 'Signing in…' : 'Sign In';
    }

    // ── Form submit ───────────────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearAllErrors();
        clearResult();

        var identifier = document.getElementById('identifier').value.trim();
        var password   = pwInput.value;          // NOT trimmed — passwords can have spaces
        var csrf       = document.getElementById('csrf_token').value;

        // Client-side validation
        var hasError = false;
        if (!identifier) {
            showFieldErr('identifier', 'Username or email is required');
            hasError = true;
        }
        if (!password) {
            showFieldErr('password', 'Password is required');
            hasError = true;
        }
        if (hasError) {
            if (!identifier) document.getElementById('identifier').focus();
            else pwInput.focus();
            return;
        }

        setLoading(true);

        // Use FormData — works correctly with PHP $_POST
        var fd = new FormData();
        fd.append('identifier', identifier);
        fd.append('password',   password);
        fd.append('csrf_token', csrf);

        fetch('/api/platform_auth', {
            method:      'POST',
            credentials: 'same-origin',
            body:        fd
        })
        .then(function (resp) {
            return resp.json().then(function (data) {
                return { status: resp.status, data: data };
            });
        })
        .then(function (res) {
            var data   = res.data  || {};
            var status = res.status;

            if (status === 200 && (data.ok === true || data.success === true)) {
                showResult('Authenticated — redirecting…', true);
                setTimeout(function () {
                    window.location.href = data.redirect || '/admin/dashboard.php';
                }, 700);
                return;
            }

            // Field-level errors
            if (data.errors && typeof data.errors === 'object') {
                Object.keys(data.errors).forEach(function (f) {
                    showFieldErr(f, data.errors[f]);
                });
            }

            showResult(data.message || 'Authentication failed. Please try again.', false);
            setLoading(false);
        })
        .catch(function (err) {
            console.error('[platform_login]', err);
            showResult('A network error occurred. Please check your connection.', false);
            setLoading(false);
        });
    });

    // Clear per-field error on input change
    ['identifier','password'].forEach(function(f){
        var inp = document.getElementById(f);
        if (inp) inp.addEventListener('input', function(){ clearFieldErr(f); inp.style.borderColor = ''; });
    });

}());
</script>
</body>
</html>
