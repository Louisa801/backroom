<?php
declare(strict_types=1);

// Item detail (customer, no login), after docs/mockups/2-tryon.png.
// Only items still in the archive are shown; otherwise a friendly "just been taken".

require_once __DIR__ . '/../src/bootstrap.php';

$id = $_GET['id'] ?? '';
$itemsRepo = new Items(db());
$item = (is_string($id) && ctype_digit($id) && strlen($id) <= 9) ? $itemsRepo->findInArchive((int) $id) : null;

// "Back to the archive" keeps the customer's filters (invalid values are dropped).
$filters = [];
foreach (['size', 'category', 'colour', 'max_price'] as $key) {
    try {
        $filters += Items::normaliseFilters([$key => $_GET[$key] ?? '']);
    } catch (InvalidArgumentException) {
        // ignore this one filter
    }
}
$query = Items::filterQuery($filters);
$backUrl = '/' . ($query !== '' ? '?' . $query : '');

$csrf = csrf_token(); // the try-on bar sends the list with fetch (M2)
$pageTitle = $item === null ? 'Item taken' : $item['title'];
$scripts = $item === null ? [] : ['tryon.js'];

if ($item === null) {
    http_response_code(404);
    require __DIR__ . '/../templates/header.php';
    ?>
    <section class="item-taken" aria-labelledby="taken-title">
        <h1 class="page__title" id="taken-title">This item has just been taken</h1>
        <p class="page__intro">Someone else asked to try it on, or it was sold. Have a look at the rest of the archive.</p>
        <a class="button button--large" href="<?= e($backUrl) ?>">Back to the archive</a>
    </section>
    <?php
    require __DIR__ . '/../templates/footer.php';
    exit;
}

$condition = Intake::CONDITIONS[$item['condition']] ?? $item['condition'];

require __DIR__ . '/../templates/header.php';
?>
<article class="item-detail" aria-labelledby="item-title">
    <div class="item-detail__photo">
        <img src="<?= e($item['photo_url']) ?>" alt="<?= e($item['title']) ?>" width="600" height="750">
    </div>
    <div class="item-detail__info">
        <a class="item-detail__back" href="<?= e($backUrl) ?>">← Back to the archive</a>
        <h1 class="item-detail__title" id="item-title"><?= e($item['title']) ?></h1>
        <p class="item-detail__meta">
            <?= e($item['category']) ?> · Size <?= e($item['size']) ?> · <?= e(ucfirst($item['colour'])) ?> · Condition: <?= e($condition) ?>
        </p>
        <p class="item-detail__price"><?= e(format_price($item['sale_price'])) ?></p>
        <button type="button" class="button button--large item-detail__tryon" data-tryon aria-pressed="false"
                aria-label="Try this on: <?= e($item['title']) ?>"
                data-id="<?= e($item['id']) ?>" data-title="<?= e($item['title']) ?>"
                data-size="<?= e($item['size']) ?>" data-photo="<?= e($item['photo_url']) ?>">Try this on</button>
        <p class="item-detail__note">Add up to <?= Requests::MAX_ITEMS ?> items to your try-on list, then send it to a fitting room.</p>
    </div>
</article>
<?php require __DIR__ . '/../templates/tryon_bar.php'; ?>
<?php require __DIR__ . '/../templates/footer.php'; ?>
