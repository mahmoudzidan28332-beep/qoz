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
    session_name('APP_SESSID');
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
    <title>Platform Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/admin/assets/css/login.css">
    <style>
        /* Platform Admin branding overrides */
        body {
            background: #0f172a;
        }
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .login-box {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        .platform-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .platform-header .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.4);
            color: #a5b4fc;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.3rem 0.75rem;
            border-radius: 99px;
            margin-bottom: 1rem;
        }
        .platform-header h1 {
            color: #f1f5f9;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 0.4rem;
        }
        .platform-header p {
            color: #94a3b8;
            font-size: 0.875rem;
            margin: 0;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            color: #cbd5e1;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.4rem;
        }
        .form-group input {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f1f5f9;
            font-size: 0.9375rem;
            padding: 0.65rem 0.875rem;
            box-sizing: border-box;
            transition: border-color 0.15s;
            outline: none;
        }
        .form-group input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .form-group input::placeholder {
            color: #475569;
        }
        .btn-login {
            width: 100%;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            margin-top: 0.5rem;
        }
        .btn-login:hover:not(:disabled) {
            background: #4f46e5;
        }
        .btn-login:active:not(:disabled) {
            transform: scale(0.98);
        }
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .result-ok {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #6ee7b7;
            border-radius: 8px;
            padding: 0.7rem 1rem;
            font-size: 0.875rem;
            margin-top: 1rem;
        }
        .result-err {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
            border-radius: 8px;
            padding: 0.7rem 1rem;
            font-size: 0.875rem;
            margin-top: 1rem;
        }
        .field-error {
            color: #f87171;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
            font-size: 0.8125rem;
            text-decoration: none;
        }
        .back-link:hover {
            color: #94a3b8;
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-box">
        <div class="platform-header">
            <div class="badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Platform Admin
            </div>
            <h1>Admin Portal</h1>
            <p>Sign in with your platform admin credentials</p>
        </div>

        <form id="platformLoginForm" action="javascript:void(0);" autocomplete="off" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

            <div class="form-group">
                <label for="identifier">Username or Email</label>
                <input
                    id="identifier"
                    name="identifier"
                    type="text"
                    placeholder="Enter username or email"
                    autocomplete="username"
                    required
                >
                <div class="field-error" id="err-identifier" style="display:none;"></div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter password"
                    autocomplete="current-password"
                    required
                >
                <div class="field-error" id="err-password" style="display:none;"></div>
            </div>

            <button type="submit" class="btn-login" id="submitBtn">Sign In</button>
        </form>

        <div id="result" role="status" aria-live="polite"></div>

        <a href="/admin/login.php" class="back-link">← Regular Admin Login</a>
    </div>
</div>

<script>
(function () {
    'use strict';

    var form       = document.getElementById('platformLoginForm');
    var submitBtn  = document.getElementById('submitBtn');
    var resultDiv  = document.getElementById('result');

    function clearErrors() {
        ['identifier', 'password'].forEach(function (f) {
            var el = document.getElementById('err-' + f);
            if (el) { el.style.display = 'none'; el.textContent = ''; }
        });
    }

    function showFieldError(field, msg) {
        var el = document.getElementById('err-' + field);
        if (el) { el.textContent = msg; el.style.display = 'block'; }
    }

    function setResult(msg, ok) {
        resultDiv.innerHTML = '<div class="' + (ok ? 'result-ok' : 'result-err') + '">' +
            escapeHtml(msg) + '</div>';
    }

    function clearResult() {
        resultDiv.innerHTML = '';
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();
        clearResult();

        var identifier = document.getElementById('identifier').value.trim();
        var password   = document.getElementById('password').value;
        var csrf       = form.querySelector('[name="csrf_token"]').value;

        // Client-side validation
        var hasError = false;
        if (!identifier) {
            showFieldError('identifier', 'Username or email is required');
            hasError = true;
        }
        if (!password) {
            showFieldError('password', 'Password is required');
            hasError = true;
        }
        if (hasError) return;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Signing in…';

        var formData = new FormData();
        formData.append('identifier', identifier);
        formData.append('password', password);
        formData.append('csrf_token', csrf);

        fetch('/api/platform_auth', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function (resp) {
            return resp.json().then(function (data) {
                return { status: resp.status, data: data };
            });
        })
        .then(function (res) {
            var data   = res.data || {};
            var status = res.status;

            if (status === 200 && (data.ok === true || data.success === true || data.message === 'Authenticated')) {
                setResult('Login successful! Redirecting…', true);
                var redirect = data.redirect || '/admin/dashboard.php';
                setTimeout(function () {
                    window.location.href = redirect;
                }, 600);
                return;
            }

            // Show field errors if present
            if (data.errors && typeof data.errors === 'object') {
                Object.keys(data.errors).forEach(function (field) {
                    showFieldError(field, data.errors[field]);
                });
            }

            setResult(data.message || 'Authentication failed', false);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Sign In';
        })
        .catch(function (err) {
            console.error(err);
            setResult('Network or server error. Please try again.', false);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Sign In';
        });
    });
}());
</script>
</body>
</html>
