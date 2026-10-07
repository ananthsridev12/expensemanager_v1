<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Easi7 Finance MCP</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#060b16;color:#e5ecff;padding:2rem;max-width:640px;margin:0 auto}
h1{font-size:1.4rem;font-weight:700;margin-bottom:.25rem}
.sub{color:#7a94c4;font-size:.88rem;margin-bottom:2rem}
.section{margin-bottom:1.5rem}
h2{font-size:.78rem;color:#7a94c4;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.75rem}
.check{display:flex;align-items:center;gap:.6rem;padding:.5rem .75rem;border-radius:8px;font-size:.88rem;margin-bottom:.4rem;background:#0b1120}
.check.ok{border-left:3px solid #22c55e}
.check.warn{border-left:3px solid #f59e0b}
.check.fail{border-left:3px solid #f43f5e}
.check .icon{font-size:1rem;flex-shrink:0}
.check .label{flex:1}
.check .detail{font-size:.78rem;color:#64748b}
code{background:#1e293b;border-radius:4px;padding:.1rem .4rem;font-size:.82rem;font-family:monospace}
.connect{margin-top:2rem;background:#0f1a2e;border-radius:12px;padding:1.25rem 1.5rem;border:1px solid rgba(120,150,210,.12)}
.connect h2{margin-bottom:.75rem}
ol{padding-left:1.2rem;font-size:.88rem;color:#94a3b8;line-height:1.8}
</style>
</head>
<body>
<h1>₹ Easi7 Finance MCP</h1>
<p class="sub">Remote MCP server — connect Claude or ChatGPT to your finance data.</p>

<div class="section">
    <h2>Setup checks</h2>

    <?php foreach ($checks as $chk): ?>
    <div class="check <?= $chk['status'] ?>">
        <span class="icon"><?= $chk['status'] === 'ok' ? '✅' : ($chk['status'] === 'warn' ? '⚠️' : '❌') ?></span>
        <span class="label"><?= htmlspecialchars($chk['label']) ?></span>
        <?php if (!empty($chk['detail'])): ?>
            <span class="detail"><?= htmlspecialchars($chk['detail']) ?></span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<div class="connect">
    <h2>Connect Claude</h2>
    <ol>
        <li>Claude.ai → Settings → Connectors → <strong>Add custom connector</strong></li>
        <li>URL: <code><?= htmlspecialchars($base) ?>/mcp</code></li>
        <li>Authentication: <strong>Sign in now</strong> → Register automatically</li>
        <li>Click Connect, sign in with your PIN</li>
        <li>Enable the connector from the tools menu in a chat</li>
    </ol>
</div>
</body>
</html>
