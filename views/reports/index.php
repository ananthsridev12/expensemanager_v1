<?php
$activeModule = 'reports';
$flash        = $flash        ?? null;
$smtpReady    = $smtpReady    ?? false;
$smtpHost     = $smtpHost     ?? '';
$smtpUser     = $smtpUser     ?? '';

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
        <span style="font-size:1.2rem;line-height:1.4;"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
        <div>
            <div style="font-weight:600;color:<?= $flash['type'] === 'success' ? '#86efac' : '#fca5a5' ?>;">
                <?= $flash['type'] === 'success' ? 'Report sent!' : 'Error' ?>
            </div>
            <div style="font-size:0.875rem;color:var(--muted);margin-top:2px;"><?= htmlspecialchars($flash['msg']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SMTP status banner -->
    <?php if (!$smtpReady): ?>
    <div style="padding:0.9rem 1.1rem;border-radius:10px;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.75rem;
                background:rgba(251,146,60,0.08);border:1px solid rgba(251,146,60,0.3);">
        <span style="font-size:1.1rem;">⚠️</span>
        <div>
            <strong style="color:#fdba74;font-size:0.9rem;">SMTP not configured</strong>
            <span style="color:var(--muted);font-size:0.85rem;margin-left:0.5rem;">— see setup instructions below before sending.</span>
        </div>
    </div>
    <?php else: ?>
    <div style="padding:0.75rem 1.1rem;border-radius:10px;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.75rem;
                background:rgba(34,197,94,0.07);border:1px solid rgba(34,197,94,0.25);">
        <span style="font-size:1.1rem;">✅</span>
        <div style="font-size:0.88rem;">
            <strong style="color:#86efac;">SMTP ready</strong>
            <span style="color:var(--muted);margin-left:0.5rem;"><?= htmlspecialchars($smtpUser) ?> via <?= htmlspecialchars($smtpHost) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Send form -->
    <section class="module-panel">
        <h2 style="margin-bottom:1.25rem;">Send email report</h2>
        <form method="post" id="report-form">
            <input type="hidden" name="form" value="send_report">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
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

                <label style="display:flex;flex-direction:column;gap:0.4rem;">
                    <span style="font-size:0.82rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">Send to</span>
                    <input type="email" name="email" required
                           placeholder="you@example.com"
                           style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.6rem 0.8rem;color:inherit;font-size:0.95rem;">
                </label>
            </div>

            <div id="custom-range" style="display:none;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <label style="display:flex;flex-direction:column;gap:0.4rem;">
                    <span style="font-size:0.82rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">From</span>
                    <input type="date" name="custom_start" id="custom-start" value="<?= date('Y-m-01') ?>"
                           style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.6rem 0.8rem;color:inherit;font-size:0.95rem;">
                </label>
                <label style="display:flex;flex-direction:column;gap:0.4rem;">
                    <span style="font-size:0.82rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">To</span>
                    <input type="date" name="custom_end" id="custom-end" value="<?= date('Y-m-d') ?>"
                           style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:0.6rem 0.8rem;color:inherit;font-size:0.95rem;">
                </label>
            </div>

            <div style="font-size:0.82rem;color:var(--muted);margin-bottom:1.25rem;padding:0.5rem 0.75rem;background:rgba(255,255,255,0.03);border-radius:6px;border-left:3px solid rgba(99,102,241,0.5);">
                Covers: <strong id="preview-label">Yesterday</strong>
            </div>

            <button type="submit" id="send-btn" <?= !$smtpReady ? 'disabled title="Configure SMTP first"' : '' ?>
                    style="border:none;border-radius:999px;padding:0.65rem 1.75rem;
                           background:<?= $smtpReady ? 'linear-gradient(135deg,#3b82f6,#6366f1)' : 'rgba(255,255,255,0.08)' ?>;
                           color:<?= $smtpReady ? '#fff' : 'var(--muted)' ?>;font-weight:700;font-size:0.9rem;
                           cursor:<?= $smtpReady ? 'pointer' : 'not-allowed' ?>;
                           box-shadow:<?= $smtpReady ? '0 4px 14px rgba(59,130,246,0.3)' : 'none' ?>;">
                📧 Send report
            </button>
        </form>
    </section>

    <!-- SMTP setup instructions -->
    <section class="module-panel" style="border-color:rgba(59,130,246,0.2);">
        <h2 style="margin-bottom:1rem;">SMTP setup — cPanel shared hosting</h2>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;margin-bottom:1.5rem;">
            <?php
            $steps = [
                ['1', '#3b82f6', 'Create an email account', 'cPanel → Email Accounts → Create. Use something like <strong>reports@yourdomain.com</strong>.'],
                ['2', '#6366f1', 'Find your SMTP details', 'cPanel → Email Accounts → click <strong>Connect Devices</strong> next to the account. Note the SSL host and port (usually 465).'],
                ['3', '#a855f7', 'Edit config/report.php', 'Fill in <code>smtp_host</code>, <code>smtp_port</code>, <code>smtp_user</code>, <code>smtp_pass</code>, and <code>smtp_from</code> with those details.'],
                ['4', '#22c55e', 'Deploy & test', 'Push the config to your server, then come back here and hit Send.'],
            ];
            foreach ($steps as [$num, $col, $title, $body]):
            ?>
            <div style="display:flex;gap:0.75rem;background:rgba(255,255,255,0.03);border-radius:10px;padding:1rem;">
                <div style="width:28px;height:28px;border-radius:50%;background:<?= $col ?>;color:#fff;font-weight:700;font-size:0.85rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= $num ?></div>
                <div>
                    <div style="font-weight:600;font-size:0.88rem;margin-bottom:4px;"><?= $title ?></div>
                    <div style="font-size:0.8rem;color:var(--muted);line-height:1.5;"><?= $body ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="background:#070d1a;border-radius:8px;padding:1rem 1.1rem;font-family:monospace;font-size:0.82rem;line-height:2;overflow-x:auto;">
            <div><span style="color:#475569;">// config/report.php</span></div>
            <div><span style="color:#94a3b8;">'smtp_host'</span> <span style="color:#64748b;">=></span> <span style="color:#86efac;">'mail.yourdomain.com'</span><span style="color:#64748b;">,</span></div>
            <div><span style="color:#94a3b8;">'smtp_port'</span> <span style="color:#64748b;">=></span> <span style="color:#93c5fd;">465</span><span style="color:#64748b;">,</span> <span style="color:#475569;">// SSL</span></div>
            <div><span style="color:#94a3b8;">'smtp_user'</span> <span style="color:#64748b;">=></span> <span style="color:#86efac;">'reports@yourdomain.com'</span><span style="color:#64748b;">,</span></div>
            <div><span style="color:#94a3b8;">'smtp_pass'</span> <span style="color:#64748b;">=></span> <span style="color:#86efac;">'your-email-password'</span><span style="color:#64748b;">,</span></div>
            <div><span style="color:#94a3b8;">'smtp_from'</span> <span style="color:#64748b;">=></span> <span style="color:#86efac;">'reports@yourdomain.com'</span><span style="color:#64748b;">,</span> <span style="color:#475569;">// must match smtp_user</span></div>
        </div>

        <p style="font-size:0.8rem;color:var(--muted);margin-top:0.75rem;margin-bottom:0;">
            <strong style="color:#94a3b8;">Note:</strong> Keep <code>smtp_from</code> identical to <code>smtp_user</code> — cPanel SMTP rejects mismatched sender addresses.
        </p>
    </section>

    <!-- Cron automation -->
    <section class="module-panel" style="border-color:rgba(99,102,241,0.2);background:rgba(99,102,241,0.03);">
        <h2 style="margin-bottom:0.75rem;">Automate daily reports</h2>
        <p style="color:var(--muted);font-size:0.88rem;margin-bottom:0.85rem;">
            Once SMTP is working, set a cron job in cPanel to receive a report every morning automatically.
        </p>
        <div style="background:#070d1a;border-radius:8px;padding:0.85rem 1rem;font-family:monospace;font-size:0.82rem;color:#93c5fd;overflow-x:auto;margin-bottom:0.75rem;">
            <div style="color:#475569;margin-bottom:4px;"># cPanel → Cron Jobs → Add New Cron Job · Schedule: 0 8 * * *</div>
            /usr/bin/php /home1/de2shrnx/personalfin.easi7.in/cron/daily_report.php
        </div>
        <p style="color:var(--muted);font-size:0.82rem;margin:0;">
            Also add your email credentials to the <code>db_*</code> and <code>smtp_*</code> fields in <code>config/report.php</code> for the cron script.
        </p>
    </section>

</main>

<script>
(function () {
    const sel    = document.getElementById('period-select');
    const custom = document.getElementById('custom-range');
    const label  = document.getElementById('preview-label');
    const cStart = document.getElementById('custom-start');
    const cEnd   = document.getElementById('custom-end');

    function fmtD(d) {
        return new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    const presets = {
        yesterday: 'Yesterday (' + fmtD(new Date(Date.now() - 864e5).toISOString().slice(0,10)) + ')',
        last7:     fmtD(new Date(Date.now() - 7*864e5).toISOString().slice(0,10)) + ' → ' + fmtD(new Date(Date.now() - 864e5).toISOString().slice(0,10)),
        thismonth: 'This month — ' + new Date().toLocaleString('default', { month: 'long', year: 'numeric' }),
        lastmonth: (function () {
            const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - 1);
            return 'Last month — ' + d.toLocaleString('default', { month: 'long', year: 'numeric' });
        })(),
    };

    function update() {
        const v = sel.value;
        if (v === 'custom') {
            custom.style.display = 'grid';
            updateCustom();
        } else {
            custom.style.display = 'none';
            label.textContent = presets[v] || '';
        }
    }

    function updateCustom() {
        const s = cStart.value, e = cEnd.value;
        if (s && e) label.textContent = s === e ? fmtD(s) : fmtD(s) + ' → ' + fmtD(e);
    }

    sel.addEventListener('change', update);
    cStart.addEventListener('change', updateCustom);
    cEnd.addEventListener('change', updateCustom);

    document.getElementById('report-form').addEventListener('submit', function () {
        const btn = document.getElementById('send-btn');
        if (btn.disabled) return;
        btn.disabled = true;
        btn.textContent = 'Sending…';
    });

    update();
})();
</script>
