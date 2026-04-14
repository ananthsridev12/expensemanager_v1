<?php
if (!function_exists('formatCurrency')) {
    function formatCurrency($value): string
    {
        return '&#8377; ' . number_format((float) $value, 2);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Finance Manager</title>

    <!-- Favicon -->
    <link rel="icon" href="public/icons/icon.svg" type="image/svg+xml">

    <!-- PWA -->
    <link rel="manifest" href="public/manifest.json">
    <meta name="theme-color" content="#0f1a2e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PersonFin">
    <link rel="apple-touch-icon" href="public/icons/icon.svg">

    <link rel="stylesheet" href="public/css/style.css?v=<?= filemtime(__DIR__ . '/../public/css/style.css') ?>">
    <script>(function(){var f=localStorage.getItem('em-font');if(f&&f!=='normal')document.documentElement.setAttribute('data-font',f);})();</script>
</head>
<body>
    <?= $content ?? '' ?>
    <script src="public/js/main.js?v=<?= filemtime(__DIR__ . '/../public/js/main.js') ?>"></script>
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/public/sw.js').catch(() => {});
    }
    </script>

    <!-- Quick-add transaction modal -->
    <div id="qa-modal-overlay" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.55);overflow-y:auto;padding:1.5rem 1rem;">
        <div id="qa-modal-box" style="position:relative;max-width:720px;margin:0 auto;background:var(--card-bg,#1a2744);border-radius:12px;padding:1.5rem;box-shadow:0 8px 40px rgba(0,0,0,0.5);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                <h2 style="margin:0;font-size:1.1rem;">Add Transaction</h2>
                <button type="button" id="qa-modal-close" aria-label="Close"
                        style="background:transparent;border:none;color:var(--muted,#9ca3af);font-size:1.5rem;cursor:pointer;line-height:1;padding:0 0.25rem;">&times;</button>
            </div>
            <div id="qa-modal-body" style="min-height:120px;">
                <p style="color:var(--muted,#9ca3af);text-align:center;padding:2rem 0;">Loading&hellip;</p>
            </div>
        </div>
    </div>

    <!-- Toast notification -->
    <div id="qa-toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:1100;background:#22c55e;color:#fff;padding:0.75rem 1.25rem;border-radius:8px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,0.3);pointer-events:none;">
        Transaction added!
    </div>

    <script>
    (function () {
        var overlay   = document.getElementById('qa-modal-overlay');
        var modalBox  = document.getElementById('qa-modal-box');
        var modalBody = document.getElementById('qa-modal-body');
        var closeBtn  = document.getElementById('qa-modal-close');
        var navBtn    = document.getElementById('quick-add-nav-btn');
        var toast     = document.getElementById('qa-toast');
        var loaded    = false;
        var toastTimer = null;

        function openModal() {
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
            if (!loaded) {
                fetch('?module=transactions&action=quick_add_form')
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        modalBody.innerHTML = html;
                        loaded = true;
                    })
                    .catch(function () {
                        modalBody.innerHTML = '<p style="color:#f43f5e;text-align:center;">Failed to load form.</p>';
                    });
            }
        }

        function closeModal() {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }

        function showToast() {
            toast.style.display = 'block';
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { toast.style.display = 'none'; }, 3000);
        }

        if (navBtn)   navBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        // Close on backdrop click (outside modal box)
        overlay.addEventListener('click', function (e) {
            if (!modalBox.contains(e.target)) closeModal();
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display !== 'none') closeModal();
        });

        // Listen for successful submission from the quick-add form
        document.addEventListener('qa-success', function () {
            closeModal();
            showToast();
            // Allow form to be re-fetched fresh next open so date resets
            loaded = false;
            modalBody.innerHTML = '<p style="color:var(--muted,#9ca3af);text-align:center;padding:2rem 0;">Loading&hellip;</p>';
        });
    })();
    </script>
</body>
</html>
