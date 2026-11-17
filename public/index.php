<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$itemsRepo = new Items(db());
$error = null;

try {
    $filters = Items::normaliseFilters($_GET);
} catch (InvalidArgumentException $e) {
    $error = $e->getMessage() . ' Showing all items instead.';
    $filters = [];
}

$items = $itemsRepo->filterArchive($filters);
$categories = $itemsRepo->categories();

$priceSteps = ['15.00', '25.00', '40.00', '60.00', '100.00'];
if (isset($filters['max_price']) && !in_array($filters['max_price'], $priceSteps, true)) {
    $priceSteps[] = $filters['max_price'];
    sort($priceSteps, SORT_NUMERIC);
}

$count = count($items);
$pageTitle = 'Archive';
$scripts = ['archive.js'];
require __DIR__ . '/../templates/header.php';
?>
<h1 class="page__title">The archive</h1>
<p class="page__intro">Everything in our back room. Filter to find what fits.</p>

<form class="filters" id="filters" method="get" action="/">
    <div class="field">
        <label for="f-size">Size</label>
        <select id="f-size" name="size">
            <option value="">Any size</option>
            <?php foreach (Items::SIZES as $size): ?>
                <option value="<?= e($size) ?>" <?= ($filters['size'] ?? '') === $size ? 'selected' : '' ?>><?= e($size) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-category">Category</label>
        <select id="f-category" name="category">
            <option value="">Any category</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category['id']) ?>" <?= ($filters['category'] ?? 0) === $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-colour">Colour</label>
        <select id="f-colour" name="colour">
            <option value="">Any colour</option>
            <?php foreach (Items::COLOURS as $colour): ?>
                <option value="<?= e($colour) ?>" <?= ($filters['colour'] ?? '') === $colour ? 'selected' : '' ?>><?= e(ucfirst($colour)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-price">Max price</label>
        <select id="f-price" name="max_price">
            <option value="">Any price</option>
            <?php foreach ($priceSteps as $step): ?>
                <option value="<?= e(rtrim(rtrim($step, '0'), '.')) ?>" <?= ($filters['max_price'] ?? '') === $step ? 'selected' : '' ?>>Up to <?= e(format_price($step)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filters__actions">
        <button type="submit" class="button" data-no-js>Show results</button>
        <a class="button button--secondary" href="/" data-clear>Clear filters</a>
    </div>
</form>

<p class="notice notice--error" id="error" role="alert" <?= $error === null ? 'hidden' : '' ?>><?= e($error ?? '') ?></p>

<p class="result-count" id="result-count" aria-live="polite"><?= $count === 1 ? '1 item' : e($count) . ' items' ?></p>

<ul class="cards" id="cards" <?= $count === 0 ? 'hidden' : '' ?>>
    <?php foreach ($items as $item) { require __DIR__ . '/../templates/item_card.php'; } ?>
</ul>

<div class="empty" id="empty" <?= $count === 0 ? '' : 'hidden' ?>>
    <h2 class="empty__title">Nothing found</h2>
    <p>No items match these filters. Try fewer filters or a higher price.</p>
    <a class="button" href="/" data-clear>Clear filters</a>
</div>

<!-- Used by archive.js. Keep in sync with templates/item_card.php. -->
<template id="card-template">
    <li class="card">
        <img class="card__photo" src="" alt="" width="400" height="500" loading="lazy">
        <div class="card__body">
            <h2 class="card__title"></h2>
            <p class="card__meta">
                <span class="card__size"></span>
                <span class="card__price"></span>
            </p>
        </div>
    </li>
</template>
<?php require __DIR__ . '/../templates/footer.php'; ?>
