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
</head>
<body<?= !empty($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
<header class="site-header">
    <a class="site-header__brand" href="/">Backroom</a>
    <?php if (!empty($staffNav)): ?>
        <nav class="staff-nav" aria-label="Staff">
            <a href="/staff/"<?= $staffNav === 'board' ? ' aria-current="page"' : '' ?>>Queue &amp; rooms</a>
            <a href="/staff/intake.php"<?= $staffNav === 'intake' ? ' aria-current="page"' : '' ?>>Buy-in</a>
        </nav>
    <?php endif; ?>
    <?php if (!empty($headerNote)): ?>
        <span class="site-header__note"><?= e($headerNote) ?></span>
    <?php endif; ?>
</header>
<main class="page">
