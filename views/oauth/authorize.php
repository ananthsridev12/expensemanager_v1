<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Authorize — Easi7 Finance</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#060b16;color:#e5ecff;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.card{width:100%;max-width:380px;background:#0f1a2e;border-radius:18px;padding:2rem;box-shadow:0 16px 48px rgba(0,0,0,.5);border:1px solid rgba(120,150,210,.12)}
.logo{font-size:2rem;text-align:center;margin-bottom:.5rem}
h1{font-size:1.1rem;font-weight:700;text-align:center;margin-bottom:.25rem}
.sub{font-size:.82rem;color:#7a94c4;text-align:center;margin-bottom:1.5rem}
.app-badge{display:inline-flex;align-items:center;gap:.4rem;background:#1e293b;border-radius:8px;padding:.4rem .8rem;font-size:.85rem;font-weight:600;margin-bottom:1.5rem;width:100%;justify-content:center}
label{display:block;font-size:.78rem;color:#7a94c4;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.4rem;margin-top:1rem}
input[type=password]{width:100%;background:#060b16;border:1px solid rgba(120,150,210,.2);border-radius:10px;padding:.8rem 1rem;font-size:1.4rem;letter-spacing:.4em;color:#e5ecff;text-align:center;outline:none;transition:border-color .2s}
input[type=password]:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.err{color:#f43f5e;font-size:.82rem;text-align:center;margin-top:.5rem}
.scopes{background:#0b1120;border-radius:10px;padding:.9rem 1rem;margin:1.2rem 0;font-size:.85rem}
.scopes p{color:#94a3b8;margin-bottom:.4rem;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em}
.scopes li{color:#cbd5e1;padding:.2rem 0;padding-left:.8rem;list-style:none}
.scopes li::before{content:"✓ ";color:#22c55e}
.btns{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:1.5rem}
.btn-allow{background:#3b82f6;color:#fff;border:none;border-radius:10px;padding:.85rem;font-size:.95rem;font-weight:600;cursor:pointer;transition:background .18s}
.btn-allow:hover{background:#2563eb}
.btn-deny{background:transparent;color:#64748b;border:1px solid #1e293b;border-radius:10px;padding:.85rem;font-size:.95rem;cursor:pointer;transition:all .18s}
.btn-deny:hover{color:#e5ecff;border-color:#475569}
</style>
</head>
<body>
<div class="card">
    <div class="logo">₹</div>
    <h1>Authorize Access</h1>
    <p class="sub">An MCP client wants to connect to your finance data</p>

    <div class="app-badge">🔗 <?= htmlspecialchars($clientName ?? '', ENT_QUOTES) ?></div>

    <div class="scopes">
        <p>Will be able to</p>
        <ul>
            <li>Read all transactions, accounts &amp; budgets</li>
            <li>Create and manage transactions</li>
            <li>Manage loans, lending &amp; borrowing</li>
            <li>Manage rental, investments &amp; notes</li>
        </ul>
    </div>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES) ?>">

        <?php if (!empty($needsPin)): ?>
            <label for="pin">Enter your PIN to confirm</label>
            <input type="password" id="pin" name="pin" inputmode="numeric" autofocus required>
            <?php if (!empty($pinError)): ?>
                <p class="err"><?= htmlspecialchars($pinError, ENT_QUOTES) ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="btns">
            <button type="submit" name="action" value="allow" class="btn-allow">Allow</button>
            <button type="submit" name="action" value="deny"  class="btn-deny">Deny</button>
        </div>
    </form>
</div>
</body>
</html>
