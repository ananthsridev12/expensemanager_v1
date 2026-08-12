<?php $activeModule = 'receipt'; ?>
<?php include __DIR__ . '/../partials/nav.php'; ?>

<style>
.receipt-page { max-width: 680px; margin: 0 auto; padding: 1.2rem 1rem 3rem; }
.receipt-page h1 { font-size: 1.3rem; font-weight: 700; color: #e5ecff; margin-bottom: 1.5rem; }

.upload-zone {
    border: 2px dashed rgba(120,150,210,0.35);
    border-radius: 14px;
    background: #0a1425;
    padding: 2rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
}
.upload-zone:hover, .upload-zone.drag-over {
    border-color: #3b82f6;
    background: #0d1e38;
}
.upload-zone input[type=file] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.upload-icon { font-size: 2.8rem; margin-bottom: 0.5rem; }
.upload-zone p { color: #7a94c4; font-size: 0.88rem; margin: 0.2rem 0 0; }
.upload-zone strong { color: #c7d8ff; }

.preview-wrap { margin-top: 1.2rem; display: none; position: relative; }
.preview-wrap img {
    width: 100%; max-height: 320px; object-fit: contain;
    border-radius: 10px; border: 1px solid rgba(120,150,210,0.2);
    background: #0a1425;
}
.preview-clear {
    position: absolute; top: 8px; right: 8px;
    background: rgba(0,0,0,0.65); border: none; color: #e5ecff;
    border-radius: 50%; width: 28px; height: 28px; cursor: pointer;
    font-size: 1rem; display: flex; align-items: center; justify-content: center;
}

.scan-btn {
    display: block; width: 100%; margin-top: 1rem;
    padding: 0.85rem; background: #3b82f6; color: #fff;
    border: none; border-radius: 10px; font-size: 1rem; font-weight: 600;
    cursor: pointer; transition: background .18s;
}
.scan-btn:hover:not(:disabled) { background: #2563eb; }
.scan-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.scan-error {
    background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3);
    color: #fca5a5; border-radius: 8px; padding: 0.7rem 1rem;
    font-size: 0.85rem; margin-top: 0.8rem; display: none;
}

.divider { border: none; border-top: 1px solid rgba(120,150,210,0.12); margin: 1.8rem 0 1.5rem; }

.tx-form { display: none; }
.tx-form.visible { display: block; }

.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
@media (max-width: 480px) { .field-row { grid-template-columns: 1fr; } }

.field { margin-bottom: 0.85rem; }
.field label {
    display: block; font-size: 0.76rem; color: #7a94c4;
    text-transform: uppercase; letter-spacing: .05em; margin-bottom: 0.35rem;
}
.field input, .field select, .field textarea {
    width: 100%; background: #060b16; border: 1px solid rgba(120,150,210,0.2);
    border-radius: 8px; padding: 0.65rem 0.85rem; font-size: 0.92rem;
    color: #e5ecff; outline: none; transition: border-color .18s; box-sizing: border-box;
}
.field input:focus, .field select:focus, .field textarea:focus {
    border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
}
.field textarea { resize: vertical; min-height: 68px; }
.field select option { background: #0f1a2e; }

.contact-wrap { position: relative; }
.contact-results {
    position: absolute; top: 100%; left: 0; right: 0; z-index: 50;
    background: #0f1a2e; border: 1px solid rgba(120,150,210,0.2); border-radius: 8px;
    margin-top: 2px; max-height: 180px; overflow-y: auto; display: none;
}
.contact-results .cr-item {
    padding: 0.6rem 0.85rem; cursor: pointer; font-size: 0.88rem; color: #c7d8ff;
    border-bottom: 1px solid rgba(120,150,210,0.08);
}
.contact-results .cr-item:hover { background: rgba(59,130,246,0.12); }
.contact-clear-btn {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: #7a94c4; cursor: pointer; font-size: 1rem;
    padding: 4px;
}

.save-btn {
    display: block; width: 100%; margin-top: 1.2rem;
    padding: 0.9rem; background: #22c55e; color: #fff;
    border: none; border-radius: 10px; font-size: 1rem; font-weight: 600;
    cursor: pointer; transition: background .18s;
}
.save-btn:hover { background: #16a34a; }

.no-key-warn {
    background: rgba(234,179,8,0.1); border: 1px solid rgba(234,179,8,0.3);
    color: #fde68a; border-radius: 10px; padding: 1rem 1.2rem;
    font-size: 0.88rem; margin-bottom: 1.5rem; line-height: 1.6;
}
.no-key-warn code { background: rgba(255,255,255,0.08); padding: 1px 5px; border-radius: 4px; font-size: 0.85em; }
</style>

<div class="receipt-page">
    <h1>&#128247; Scan Receipt</h1>

    <?php if (!$apiKeySet): ?>
    <div class="no-key-warn">
        <strong>Gemini API key not configured.</strong><br>
        Open <code>config/gemini.php</code> and paste your key in the <code>api_key</code> field.<br>
        Get a free key at <strong>aistudio.google.com/apikey</strong> — no billing required for the free tier.
    </div>
    <?php endif; ?>

    <!-- Upload area -->
    <div class="upload-zone" id="uploadZone">
        <input type="file" id="receiptFile" accept="image/*" capture="environment">
        <div class="upload-icon">&#128247;</div>
        <strong>Tap to take a photo or choose an image</strong>
        <p>JPEG &middot; PNG &middot; WebP &middot; max 10 MB</p>
    </div>

    <div class="preview-wrap" id="previewWrap">
        <img id="previewImg" src="" alt="Receipt preview">
        <button type="button" class="preview-clear" id="clearBtn" title="Remove">&times;</button>
    </div>

    <button type="button" class="scan-btn" id="scanBtn" disabled>
        <span id="scanLabel">&#128269; Scan with Gemini AI</span>
    </button>

    <div class="scan-error" id="scanError"></div>

    <hr class="divider">

    <!-- Transaction form — shown after scan (or can fill manually) -->
    <form method="post" action="?module=receipt" id="txForm" class="tx-form visible">
        <input type="hidden" name="form" value="create">

        <p style="color:#7a94c4;font-size:0.85rem;margin:0 0 1.2rem;">
            Fields below are pre-filled after scanning. Review, add the account and category, then save.
        </p>

        <div class="field-row">
            <div class="field">
                <label>Date</label>
                <input type="date" name="transaction_date" id="txDate" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="field">
                <label>Amount (&#8377;)</label>
                <input type="number" name="amount" id="txAmount" step="0.01" min="0.01" placeholder="0.00" required>
            </div>
        </div>

        <div class="field">
            <label>Notes / Description</label>
            <textarea name="notes" id="txNotes" placeholder="e.g. D-Mart groceries"></textarea>
        </div>

        <div class="field">
            <label>Account <span style="color:#f43f5e">*</span></label>
            <select name="account_id" required>
                <option value="">— select account —</option>
                <?php foreach (groupAccountsForSelect($accounts) as $grp): ?>
                <optgroup label="<?= htmlspecialchars($grp['label']) ?>">
                    <?php foreach ($grp['accounts'] as $acc): ?>
                    <option value="<?= htmlspecialchars($acc['account_type'] . ':' . $acc['id']) ?>">
                        <?= htmlspecialchars($acc['bank_name'] . ' — ' . $acc['account_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field-row">
            <div class="field">
                <label>Category</label>
                <select name="category_id" id="txCategory">
                    <option value="">— none —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Subcategory</label>
                <select name="subcategory_id" id="txSubcategory">
                    <option value="">— none —</option>
                </select>
            </div>
        </div>

        <div class="field">
            <label>Payment Method</label>
            <select name="payment_method_id">
                <option value="">— none —</option>
                <?php foreach ($paymentMethods as $pm): ?>
                <option value="<?= (int) $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label>Contact / Vendor</label>
            <div class="contact-wrap">
                <input type="text" id="contactSearch" placeholder="Search contact..." autocomplete="off">
                <button type="button" class="contact-clear-btn" id="contactClearBtn" style="display:none;">&times;</button>
                <input type="hidden" name="contact_id" id="contactId">
                <div class="contact-results" id="contactResults"></div>
            </div>
        </div>

        <button type="submit" class="save-btn">&#128190; Save Transaction</button>
    </form>
</div>

<script>
(function () {
    // ── subcategory map ──────────────────────────────────────────────────────
    const subcatMap = {
        <?php foreach ($categories as $cat): ?>
        <?= (int) $cat['id'] ?>: [
            <?php foreach ($cat['subcategories'] ?? [] as $sc): ?>
            {id: <?= (int) $sc['id'] ?>, name: <?= json_encode($sc['name']) ?>},
            <?php endforeach; ?>
        ],
        <?php endforeach; ?>
    };

    const catSel  = document.getElementById('txCategory');
    const subSel  = document.getElementById('txSubcategory');
    function refreshSubcats() {
        const subs = subcatMap[catSel.value] || [];
        subSel.innerHTML = '<option value="">— none —</option>';
        subs.forEach(s => {
            const o = document.createElement('option');
            o.value = s.id; o.textContent = s.name;
            subSel.appendChild(o);
        });
    }
    catSel.addEventListener('change', refreshSubcats);

    // ── contact search ───────────────────────────────────────────────────────
    const contactInput   = document.getElementById('contactSearch');
    const contactId      = document.getElementById('contactId');
    const contactResults = document.getElementById('contactResults');
    const contactClear   = document.getElementById('contactClearBtn');
    let contactTimer;

    contactInput.addEventListener('input', () => {
        clearTimeout(contactTimer);
        const q = contactInput.value.trim();
        if (q.length < 2) { contactResults.style.display = 'none'; return; }
        contactTimer = setTimeout(() => {
            fetch('?module=receipt&action=contact_search&q=' + encodeURIComponent(q))
                .then(r => r.json()).then(list => {
                    contactResults.innerHTML = '';
                    if (!list.length) { contactResults.style.display = 'none'; return; }
                    list.forEach(c => {
                        const d = document.createElement('div');
                        d.className = 'cr-item';
                        d.textContent = c.name;
                        d.addEventListener('click', () => {
                            contactInput.value = c.name;
                            contactId.value    = c.id;
                            contactClear.style.display = 'block';
                            contactResults.style.display = 'none';
                        });
                        contactResults.appendChild(d);
                    });
                    contactResults.style.display = 'block';
                });
        }, 250);
    });
    contactClear.addEventListener('click', () => {
        contactInput.value = ''; contactId.value = '';
        contactClear.style.display = 'none';
    });
    document.addEventListener('click', e => {
        if (!contactResults.contains(e.target) && e.target !== contactInput)
            contactResults.style.display = 'none';
    });

    // ── upload & preview ─────────────────────────────────────────────────────
    const fileInput   = document.getElementById('receiptFile');
    const previewWrap = document.getElementById('previewWrap');
    const previewImg  = document.getElementById('previewImg');
    const clearBtn    = document.getElementById('clearBtn');
    const scanBtn     = document.getElementById('scanBtn');
    const scanLabel   = document.getElementById('scanLabel');
    const scanError   = document.getElementById('scanError');
    const uploadZone  = document.getElementById('uploadZone');

    let selectedBlob = null;

    function showPreview(blob) {
        selectedBlob = blob;
        previewImg.src = URL.createObjectURL(blob);
        previewWrap.style.display = 'block';
        scanBtn.disabled = false;
    }

    function clearAll() {
        selectedBlob = null;
        previewImg.src = '';
        previewWrap.style.display = 'none';
        scanBtn.disabled = true;
        fileInput.value = '';
        scanError.style.display = 'none';
    }
    clearBtn.addEventListener('click', clearAll);

    // Resize image client-side before sending (max 1200px)
    function resizeAndLoad(file) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = new Image();
            img.onload = () => {
                const MAX = 1200;
                let w = img.width, h = img.height;
                if (w > MAX || h > MAX) {
                    if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
                    else       { w = Math.round(w * MAX / h); h = MAX; }
                }
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                canvas.toBlob(blob => showPreview(blob), 'image/jpeg', 0.88);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) resizeAndLoad(fileInput.files[0]);
    });

    // Drag & drop
    uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('drag-over'); });
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
    uploadZone.addEventListener('drop', e => {
        e.preventDefault(); uploadZone.classList.remove('drag-over');
        const file = e.dataTransfer?.files[0];
        if (file && file.type.startsWith('image/')) resizeAndLoad(file);
    });

    // ── scan ─────────────────────────────────────────────────────────────────
    scanBtn.addEventListener('click', async () => {
        if (!selectedBlob) return;

        scanBtn.disabled = true;
        scanLabel.textContent = '⏳ Scanning…';
        scanError.style.display = 'none';

        const fd = new FormData();
        fd.append('form', 'scan');
        fd.append('receipt', selectedBlob, 'receipt.jpg');

        try {
            const res  = await fetch('?module=receipt', { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.ok) {
                scanError.textContent = data.error || 'Scan failed.';
                scanError.style.display = 'block';
            } else {
                // Pre-fill form fields
                if (data.date)   document.getElementById('txDate').value   = data.date;
                if (data.amount) document.getElementById('txAmount').value = data.amount.toFixed(2);
                if (data.notes)  document.getElementById('txNotes').value  = data.notes;

                // Scroll form into view
                document.getElementById('txForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch (err) {
            scanError.textContent = 'Request failed: ' + err.message;
            scanError.style.display = 'block';
        } finally {
            scanBtn.disabled = false;
            scanLabel.textContent = '🔍 Scan with Gemini AI';
        }
    });
})();
</script>
