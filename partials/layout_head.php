<!DOCTYPE html>
<html lang="en" data-mode="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Azzurro HR'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;1,14..32,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo baseUrl('css/style.css'); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Fade-in animation for main content */
        .main {
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── Global Toast Notification System ── */
        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .toast {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 280px;
            max-width: 380px;
            padding: 14px 18px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            pointer-events: all;
            cursor: default;
            animation: toastSlideIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
        }
        .toast.toast-success { background: linear-gradient(135deg, #059669, #10b981); }
        .toast.toast-error   { background: linear-gradient(135deg, #dc2626, #ef4444); }
        .toast.toast-info    { background: linear-gradient(135deg, #2563eb, #3b82f6); }
        .toast-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
        .toast-body { flex: 1; line-height: 1.4; }
        .toast-dismiss {
            flex-shrink: 0;
            background: none;
            border: none;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            padding: 0;
            font-size: 14px;
            line-height: 1;
            margin-top: 1px;
            transition: color 0.2s;
        }
        .toast-dismiss:hover { color: #fff; }
        .toast.toast-hide {
            animation: toastSlideOut 0.3s ease-in forwards;
        }
        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateX(60px) scale(0.92); }
            to   { opacity: 1; transform: translateX(0)   scale(1); }
        }
        @keyframes toastSlideOut {
            from { opacity: 1; transform: translateX(0)   scale(1); }
            to   { opacity: 0; transform: translateX(60px) scale(0.92); }
        }
    </style>
</head>
<body>
<!-- Global Toast Container -->
<div id="toast-container"></div>

<script>
    // ── Global CSRF Token (active immediately, before any .ready()) ──
    const CSRF_TOKEN = '<?php echo \App\Utils\AppHelpers::generateCsrfToken(); ?>';

    // Inject CSRF token into every jQuery POST before any page script runs
    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
        if (options.type && options.type.toUpperCase() === 'POST') {
            if (typeof options.data === 'string') {
                options.data += (options.data ? '&' : '') + 'csrf_token=' + encodeURIComponent(CSRF_TOKEN);
            } else if (options.data && typeof options.data === 'object' && !(options.data instanceof FormData)) {
                options.data.csrf_token = CSRF_TOKEN;
            } else if (!options.data) {
                options.data = 'csrf_token=' + encodeURIComponent(CSRF_TOKEN);
            } else if (options.data instanceof FormData) {
                options.data.append('csrf_token', CSRF_TOKEN);
            }
        }
    });

    // ── Global Toast Notification Helper ──
    // Usage: showToast('Message here', 'success' | 'error' | 'info')
    function showToast(message, type) {
        type = type || 'info';
        const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
        const icon  = icons[type] || icons.info;

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = [
            '<i class="fa-solid ' + icon + ' toast-icon"></i>',
            '<span class="toast-body">' + message + '</span>',
            '<button class="toast-dismiss" onclick="dismissToast(this.parentElement)"><i class="fa-solid fa-xmark"></i></button>'
        ].join('');

        document.getElementById('toast-container').appendChild(toast);

        // Auto-dismiss after 4 seconds
        const timer = setTimeout(function() { dismissToast(toast); }, 4000);
        toast._timer = timer;
    }

    function dismissToast(el) {
        if (!el || el.classList.contains('toast-hide')) return;
        clearTimeout(el._timer);
        el.classList.add('toast-hide');
        setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 320);
    }
</script>
