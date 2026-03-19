<?php $module = $activeModule ?? 'dashboard'; ?>
<button type="button" class="menu-toggle" id="menu-toggle" aria-label="Open menu" aria-expanded="false">Menu</button>
<div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
<nav class="main-nav" id="main-nav" aria-label="Primary">
    <div class="nav-head">
        <span class="brand">Easi7 Finance</span>
        <button type="button" class="nav-close" id="nav-close" aria-label="Close menu">Close</button>
    </div>
    <a href="?module=dashboard" class="<?= $module === 'dashboard' ? 'is-active' : '' ?>">Dashboard</a>
    <a href="?module=analytics" class="<?= $module === 'analytics' ? 'is-active' : '' ?>">Analytics</a>
    <a href="?module=accounts" class="<?= $module === 'accounts' ? 'is-active' : '' ?>">Accounts</a>
    <a href="?module=contacts" class="<?= $module === 'contacts' ? 'is-active' : '' ?>">Contacts</a>
    <a href="?module=categories" class="<?= $module === 'categories' ? 'is-active' : '' ?>">Categories</a>
    <a href="?module=transactions" class="<?= $module === 'transactions' ? 'is-active' : '' ?>">Transactions</a>
    <a href="?module=reminders" class="<?= $module === 'reminders' ? 'is-active' : '' ?>">Reminders</a>
    <a href="?module=loans" class="<?= $module === 'loans' ? 'is-active' : '' ?>">Loans</a>
    <a href="?module=lending" class="<?= $module === 'lending' ? 'is-active' : '' ?>">Lending</a>
    <a href="?module=investments" class="<?= $module === 'investments' ? 'is-active' : '' ?>">Investments</a>
    <a href="?module=sip" class="<?= $module === 'sip' ? 'is-active' : '' ?>">SIP</a>
    <a href="?module=rental" class="<?= $module === 'rental' ? 'is-active' : '' ?>">Rental</a>
</nav>
