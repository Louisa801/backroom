<?php
/** @var string $pageTitle */
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?> · Backroom</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&family=Fraunces:opsz,wght@9..144,600&display=swap">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body<?= !empty($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
<?php if (!empty($staffNav)): ?>
<?php $staffMember = current_staff(); ?>
<!-- Staff header: one row. Below 900px the menu folds into a dropdown (staff-menu.js). -->
<header class="site-header site-header--staff">
    <a class="site-header__brand" href="/">Backroom</a>
    <button type="button" class="staff-menu-button" aria-expanded="false" aria-controls="staff-menu">
        <span class="staff-menu-button__icon" aria-hidden="true">☰</span> Menu
    </button>
    <div class="staff-menu" id="staff-menu">
        <nav class="staff-nav" aria-label="Staff">
            <a href="/staff/"<?= $staffNav === 'board' ? ' aria-current="page"' : '' ?>>Queue &amp; rooms</a>
            <a href="/staff/intake.php"<?= $staffNav === 'intake' ? ' aria-current="page"' : '' ?>>Buy-in</a>
            <a href="/manager/"<?= $staffNav === 'dashboard' ? ' aria-current="page"' : '' ?>>Dashboard</a>
        </nav>
        <?php if ($staffMember !== null): ?>
            <form class="staff-user" method="post" action="/staff/logout.php">
                <span class="staff-user__name"><span class="visually-hidden">Logged in as </span><?= e($staffMember['name']) ?></span>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="staff-user__logout">Log out</button>
            </form>
        <?php endif; ?>
    </div>
</header>
<script src="/assets/js/staff-menu.js" defer></script>
<?php else: ?>
<header class="site-header">
    <a class="site-header__brand" href="/">Backroom</a>
    <?php if (!empty($headerNote)): ?>
        <span class="site-header__note"><?= e($headerNote) ?></span>
    <?php endif; ?>
</header>
<?php endif; ?>
<main class="page">
