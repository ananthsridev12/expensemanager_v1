<?php
$activeModule = 'notes';
$notes        = $notes    ?? [];
$editNote     = $editNote ?? null;

include __DIR__ . '/../partials/nav.php';
?>

<main class="module-content">
    <header class="module-header">
        <h1>Notes</h1>
        <p>Keep quick notes and reminders for yourself.</p>
    </header>

    <!-- Write / edit form -->
    <section class="module-panel">
        <h2 style="margin-bottom:1rem;"><?= $editNote ? 'Edit note' : 'New note' ?></h2>
        <form method="post" id="note-form">
            <input type="hidden" name="form"    value="note_save">
            <input type="hidden" name="note_id" value="<?= $editNote ? (int)$editNote['id'] : 0 ?>">
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                <input
                    type="text"
                    name="title"
                    placeholder="Title (optional)"
                    value="<?= htmlspecialchars($editNote['title'] ?? '') ?>"
                    style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.6rem 0.9rem;color:inherit;font-size:0.95rem;font-family:inherit;">
                <textarea
                    name="content"
                    rows="5"
                    placeholder="Write your note here…"
                    required
                    style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.6rem 0.9rem;color:inherit;font-size:0.95rem;font-family:inherit;resize:vertical;line-height:1.6;"><?= htmlspecialchars($editNote['content'] ?? '') ?></textarea>
                <div style="display:flex;gap:0.75rem;align-items:center;">
                    <button type="submit"
                            style="border:none;border-radius:999px;padding:0.55rem 1.4rem;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;font-weight:700;font-size:0.875rem;cursor:pointer;box-shadow:0 4px 14px rgba(59,130,246,0.3);">
                        <?= $editNote ? 'Update note' : 'Save note' ?>
                    </button>
                    <?php if ($editNote): ?>
                        <a href="?module=notes" class="secondary" style="padding:0.55rem 1.1rem;border-radius:999px;font-size:0.875rem;">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </section>

    <!-- Notes list -->
    <?php if (empty($notes)): ?>
    <section class="module-panel">
        <div class="module-placeholder">
            <p>No notes yet. Write your first note above.</p>
        </div>
    </section>
    <?php else: ?>
    <section class="module-panel">
        <h2 style="margin-bottom:1rem;"><?= count($notes) ?> note<?= count($notes) !== 1 ? 's' : '' ?></h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
        <?php foreach ($notes as $note):
            $title     = $note['title'] !== '' ? htmlspecialchars($note['title']) : null;
            $content   = htmlspecialchars($note['content']);
            $updatedAt = date('d M Y, g:i a', strtotime($note['updated_at']));
            $isEditing = $editNote && (int)$editNote['id'] === (int)$note['id'];
        ?>
            <article style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,<?= $isEditing ? '0.2' : '0.07' ?>);border-radius:12px;padding:1rem 1.1rem;display:flex;flex-direction:column;gap:0.6rem;<?= $isEditing ? 'box-shadow:0 0 0 2px rgba(99,102,241,0.4);' : '' ?>">
                <?php if ($title): ?>
                    <div style="font-weight:600;font-size:0.95rem;"><?= $title ?></div>
                <?php endif; ?>
                <div style="color:var(--text);font-size:0.88rem;line-height:1.65;white-space:pre-wrap;word-break:break-word;"><?= $content ?></div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.25rem;gap:0.5rem;">
                    <span style="font-size:0.75rem;color:var(--muted);"><?= $updatedAt ?></span>
                    <div style="display:flex;gap:0.4rem;">
                        <a href="?module=notes&edit=<?= (int)$note['id'] ?>"
                           style="font-size:0.78rem;padding:0.25rem 0.65rem;border:1px solid rgba(255,255,255,0.12);border-radius:6px;color:var(--muted);text-decoration:none;line-height:1.4;"
                           title="Edit">Edit</a>
                        <form method="post" style="margin:0;" onsubmit="return confirm('Delete this note?')">
                            <input type="hidden" name="form"    value="note_delete">
                            <input type="hidden" name="note_id" value="<?= (int)$note['id'] ?>">
                            <button type="submit"
                                    style="font-size:0.78rem;padding:0.25rem 0.65rem;border:1px solid rgba(244,63,94,0.3);border-radius:6px;background:none;color:#f43f5e;cursor:pointer;line-height:1.4;"
                                    title="Delete">Delete</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>
