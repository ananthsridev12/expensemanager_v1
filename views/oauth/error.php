<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Authorization Error — Easi7 Finance</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#060b16;color:#e5ecff;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.card{width:100%;max-width:380px;background:#0f1a2e;border-radius:18px;padding:2rem;box-shadow:0 16px 48px rgba(0,0,0,.5);border:1px solid rgba(255,60,60,.15);text-align:center}
.icon{font-size:2.5rem;margin-bottom:.75rem}
h1{font-size:1.1rem;font-weight:700;margin-bottom:.5rem;color:#f43f5e}
p{font-size:.88rem;color:#94a3b8;line-height:1.5}
a{color:#3b82f6;text-decoration:none;display:inline-block;margin-top:1.2rem;font-size:.9rem}
</style>
</head>
<body>
<div class="card">
    <div class="icon">⚠️</div>
    <h1>Authorization Error</h1>
    <p><?= htmlspecialchars($message ?? 'An error occurred during authorization.', ENT_QUOTES) ?></p>
    <a href="javascript:history.back()">← Go back</a>
</div>
</body>
</html>
