<?php
$activeModule = 'reports';
$flash        = $flash ?? null;

include __DIR__ . '/../partials/nav.php';
?>

<main class="module-content">
    <header class="module-header">
        <h1>Reports</h1>
        <p>Generate and email expense snapshots for any period.</p>
    </header>

    <?php if ($flash): ?>
    <div style="padding:1rem 1.25rem;border-radius:10px;margin-bottom:0.5rem;display:flex;align-items:flex-start;gap:0.75rem;
                background:<?= $flash['type'] === 'success' ? 'rgba(34,197,94,0.1)' : 'rgba(244,63,94,0.1)' ?>;
                border:1px solid <?= $flash['type'] === 'success' ? 'rgba(34,197,94,0.35)' : 'rgba(244,63,94,0.35)' ?>;">
        <span style="font-size:1.2rem;line-height:1;"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
        <div>
            <div style="font-weight:600;color:<?= $flash['type'] === 'success' ? '#86efac' : '#fca5a5' ?>;">
                <?= $flash['type'] === 'success' ? 'Report sent!' : 'Failed to send' ?>
            </div>
            <div style="font-size:0.875rem;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($flash['msg']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Send report form -->
    <section class="module-panel">
        <h2 style="margin-bottom:1.25rem;">Send email report</h2>
        <form method="post" id="report-form">
            <input type="hidden" name="form" value="send_report">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">

                <!-- Period picker -->
                <label style="display:flex;flex-direction:column;gap:0.4rem;">
                    <span style="font-size:0.82rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">Period</span>
                    <select name="period" id="period-select"
                            style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.6rem 0.8rem;color:inherit;font-size:0.95rem;cursor:pointer;">
                        <option value="yesterday">Yesterday</option>
                        <option value="last7">Last 7 days</option>
                        <option value="thismonth">This month</option>
                        <option value="lastmonth">Last month</option>
                        <option value="custom">Custom range…</option>
                    </select>
                </label>

                <!-- Email -->
                <label style="display:flex;flex-direction:column;gap:0.4rem;">
                    <span style="font-size:0.82rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">Send to</span>
                    <input type="email" name="email" id="report-email" required
                           placeholder="you@example.com"
                           style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.6rem 0.8rem;color:inherit;font-size:0.95rem;">
                </label>
            </div>

            <!-- Custom date range (shown only when custom is selected) -->
            <div id="custom-range" style="display:none;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <label style="display:flex;flex-direction:column;gap:0.4rem;">
                    <span style="font-size:0.82rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">From</span>
                    <input type="date" name="custom_start" id="custom-start"
                           value="<?= date('Y-m-01') ?>"
                           style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.6rem 0.8rem;color:inherit;font-size:0.95rem;">
                </label>
                <label style="display:flex;flex-direction:column;gap:0.4rem;">
                    <span style="font-size:0.82rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">To</span>
                    <input type="date" name="custom_end" id="custom-end"
                           value="<?= date('Y-m-d') ?>"
                           style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.6rem 0.8rem;color:inherit;font-size:0.95rem;">
                </label>
            </div>

            <!-- Period preview -->
            <div id="period-preview" style="font-size:0.82rem;color:var(--muted);margin-bottom:1.25rem;padding:0.5rem 0.75rem;background:rgba(255,255,255,0.03);border-radius:6px;border-left:3px solid rgba(99,102,241,0.5);">
                Covers: <strong id="preview-label">Yesterday</strong>
            </div>

            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <button type="submit" id="send-btn"
                        style="border:none;border-radius:999px;padding:0.65rem 1.75rem;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;font-weight:700;font-size:0.9rem;cursor:pointer;box-shadow:0 4px 14px rgba(59,130,246,0.3);display:flex;align-items:center;gap:0.5rem;">
                    <span>📧</span> Send report
                </button>
                <span style="font-size:0.8rem;color:var(--muted);">Report is sent via server mail(). Check spam if not received.</span>
            </div>
        </form>
    </section>

    <!-- What's included -->
    <section class="module-panel">
        <h2 style="margin-bottom:1rem;">What's included in the report</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.75rem;">
            <?php
            $items = [
                ['📊', 'Summary cards', 'Total spent, income, and net for the period'],
                ['🏷️', 'By category', 'Expense breakdown with % share and bar chart'],
                ['💳', 'Top 5 transactions', 'Biggest individual expenses'],
                ['📅', 'Date range', 'Clear period label in subject and header'],
            ];
            foreach ($items as [$icon, $title, $desc]): ?>
            <div style="display:flex;gap:0.75rem;align-items:flex-start;background:rgba(255,255,255,0.03);border-radius:8px;padding:0.85rem;">
                <span style="font-size:1.3rem;flex-shrink:0;"><?= $icon ?></span>
                <div>
                    <div style="font-weight:600;font-size:0.88rem;margin-bottom:2px;"><?= $title ?></div>
                    <div style="font-size:0.78rem;color:var(--muted);line-height:1.45;"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Cron tip -->
    <section class="module-panel" style="border-color:rgba(99,102,241,0.25);background:rgba(99,102,241,0.04);">
        <h2 style="margin-bottom:0.75rem;">Automate daily reports via cPanel</h2>
        <p style="color:var(--muted);font-size:0.88rem;margin-bottom:0.85rem;">
            To get a daily snapshot automatically every morning, set up a cron job in cPanel:
        </p>
        <div style="background:#070d1a;border-radius:8px;padding:0.85rem 1rem;font-family:monospace;font-size:0.82rem;color:#93c5fd;overflow-x:auto;margin-bottom:0.75rem;">
            <div style="color:#475569;margin-bottom:4px;"># cPanel → Cron Jobs → Add New (schedule: 0 8 * * *)</div>
            /usr/bin/php /home1/de2shrnx/personalfin.easi7.in/cron/daily_report.php
        </div>
        <p style="color:var(--muted);font-size:0.82rem;margin:0;">
            Fill in <code style="background:rgba(255,255,255,0.08);padding:1px 5px;border-radius:4px;">config/report.php</code> with your email and DB credentials first.
        </p>
    </section>

</main>

<script>
(function () {
    const sel     = document.getElementById('period-select');
    const custom  = document.getElementById('custom-range');
    const preview = document.getElementById('preview-label');
    const cStart  = document.getElementById('custom-start');
    const cEnd    = document.getElementById('custom-end');

    const labels = {
        yesterday:  'Yesterday (' + fmtDate(new Date(Date.now() - 864e5)) + ')',
        last7:      fmtDate(new Date(Date.now() - 7 * 864e5)) + ' → ' + fmtDate(new Date(Date.now() - 864e5)),
        thismonth:  'This month — ' + new Date().toLocaleString('default', { month: 'long', year: 'numeric' }),
        lastmonth:  (function () {
            const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - 1);
            return 'Last month — ' + d.toLocaleString('default', { month: 'long', year: 'numeric' });
        })(),
    };

    function fmtDate(d) {
        return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function update() {
        const v = sel.value;
        if (v === 'custom') {
            custom.style.display = 'grid';
            updateCustomPreview();
        } else {
            custom.style.display = 'none';
            preview.textContent = labels[v] || '';
        }
    }

    function updateCustomPreview() {
        const s = cStart.value, e = cEnd.value;
        if (s && e) {
            const ds = new Date(s + 'T00:00:00'), de = new Date(e + 'T00:00:00');
            preview.textContent = s === e ? fmtDate(ds) : fmtDate(ds) + ' → ' + fmtDate(de);
        }
    }

    sel.addEventListener('change', update);
    cStart.addEventListener('change', updateCustomPreview);
    cEnd.addEventListener('change', updateCustomPreview);

    // Disable send button on submit to prevent double-send
    document.getElementById('report-form').addEventListener('submit', function () {
        const btn = document.getElementById('send-btn');
        btn.disabled = true;
        btn.textContent = 'Sending…';
    });

    update();
})();
</script>
