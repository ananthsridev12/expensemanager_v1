<?php
$activeModule = 'credit_cards';
$cards = $cards ?? [];
$summary = $summary ?? ['count' => 0, 'total_limit' => 0.0, 'total_outstanding' => 0.0];

include __DIR__ . '/../partials/nav.php';
?>
<main class="module-content">
    <header class="module-header">
        <h1>Credit cards</h1>
        <p>Track limits, billing cycles, and outstanding balances without mixing with ledger entries.</p>
    </header>

    <section class="summary-cards">
        <article class="card">
            <h3>Total cards</h3>
            <p><?= $summary['count'] ?></p>
        </article>
        <article class="card">
            <h3>Total limit</h3>
            <p><?= formatCurrency($summary['total_limit']) ?></p>
        </article>
        <article class="card">
            <h3>Outstanding</h3>
            <p><?= formatCurrency($summary['total_outstanding']) ?></p>
        </article>
    </section>

    <section class="module-panel">
        <h2>Manage cards from Accounts</h2>
        <p class="muted">Create or edit credit cards from the <a href="?module=accounts">Accounts module</a> using account type = Credit card.</p>
    </section>

    <section class="module-panel">
        <h2>Card list</h2>
        <?php if (empty($cards)): ?>
            <p class="muted">No credit cards tracked yet.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Bank</th>
                            <th>Card</th>
                            <th>Limit</th>
                            <th>Outstanding</th>
                            <th>Billing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cards as $card): ?>
                            <tr>
                                <td><?= htmlspecialchars($card['bank_name']) ?></td>
                                <td><?= htmlspecialchars($card['card_name']) ?></td>
                                <td><?= formatCurrency((float) $card['credit_limit']) ?></td>
                                <td><?= formatCurrency((float) $card['outstanding_balance']) ?></td>
                                <td><?= htmlspecialchars($card['billing_date']) ?> / <?= htmlspecialchars($card['due_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
