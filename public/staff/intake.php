<?php
declare(strict_types=1);

// M5: buy-in from a walk-in seller. (Login is added in the login story.)

require_once __DIR__ . '/../../src/bootstrap.php';

/** Render one item block in its own scope. */
function render_intake_item(string|int $key, int|string $number, array $row, array $rowErrors, array $categories): void
{
    require __DIR__ . '/../../templates/intake_item.php';
}

$pdo = db();
$intake = new Intake($pdo);
$photos = new Photos($pdo);
$categories = (new Items($pdo))->categories();

start_session();
$photos->deleteStalePending();

$blankRow = array_fill_keys(Intake::FIELDS, '') + ['photo_id' => null];
$rows = [];
$payoutType = '';
$errors = [];
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payoutType = is_string($_POST['payout_type'] ?? null) ? $_POST['payout_type'] : '';

    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // PHP drops the whole form when it is bigger than post_max_size.
        $formError = 'The photos together were too large to send at once, so nothing was saved. Please use fewer or smaller photos.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(403);  // refused: the form was not sent with this session's token
        $formError = 'This form had expired, so nothing was saved. Please check the details, add the photos again and save.';
        $rows = Intake::rowsFromRequest($_POST, []);
    } else {
        $rows = Intake::rowsFromRequest($_POST, $_FILES);

        $pending = array_values(array_filter($_SESSION['pending_photos'] ?? [], 'is_int'));
        if (count($rows) <= Intake::MAX_ITEMS) {
            foreach ($rows as $i => $row) {
                [$rows[$i]['photo_id'], $rows[$i]['photo_error']] = $photos->fromForm($row['file'], $row['pending_photo'], $pending);
            }
        }
        $_SESSION['pending_photos'] = array_slice(array_values(array_unique($pending)), -50);

        $result = Intake::validate($rows, $payoutType, array_column($categories, 'id'), $intake->takenShelfCodes());
        $errors = $result['errors'];

        if ($errors === []) {
            try {
                $buyInId = $intake->save($result['data'], current_staff_id($pdo));
                $_SESSION['pending_photos'] = array_values(array_diff($_SESSION['pending_photos'], array_column($result['data']['items'], 'photo_id')));
                header('Location: /staff/intake.php?saved=' . $buyInId, true, 303);
                exit;
            } catch (ShelfCodeTakenException $taken) {
                foreach ($result['data']['items'] as $i => $item) {
                    if (in_array($item['shelf_code'], $taken->codes, true)) {
                        $errors['rows'][$i]['shelf_code'] = "Shelf {$item['shelf_code']} was just taken on another screen. Please choose another spot.";
                    }
                }
            }
        }
    }
}

// Confirmation after saving (POST, then redirect here, so refresh can't save twice).
$saved = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['saved']) && is_string($_GET['saved']) && ctype_digit($_GET['saved'])) {
    $saved = $intake->summary((int) $_GET['saved']);
}

$suggestions = Intake::suggestShelfCodes($intake->takenShelfCodes(), Intake::MAX_ITEMS * 2);
if ($rows === []) {
    $rows = [['shelf_code' => $suggestions[0]] + $blankRow];
}

// Error summary at the top, with links to the fields.
$fieldLabels = [
    'photo' => 'photo', 'title' => 'title', 'category' => 'category', 'size' => 'size', 'colour' => 'colour',
    'condition' => 'condition', 'purchase_price' => 'purchase price', 'sale_price' => 'sale price', 'shelf_code' => 'shelf code',
];
$summary = [];
if (isset($errors['payout_type'])) {
    $summary[] = ['#payout-cash', 'Payout: ' . $errors['payout_type']];
}
if (isset($errors['items'])) {
    $summary[] = ['#items', $errors['items']];
}
foreach ($errors['rows'] ?? [] as $i => $rowErrors) {
    foreach ($fieldLabels as $field => $label) {
        if (isset($rowErrors[$field])) {
            $summary[] = ["#item-$i-$field", 'Item ' . ($i + 1) . ', ' . $label . ': ' . $rowErrors[$field]];
        }
    }
}

$pageTitle = 'Buy-in';
$headerNote = 'Staff';
$staffNav = 'intake';
$scripts = ['intake.js'];
require __DIR__ . '/../../templates/header.php';
?>

<?php if ($saved !== null): ?>
    <section class="success" aria-labelledby="saved-title">
        <h1 id="saved-title" class="success__title">Buy-in #<?= e($saved['id']) ?> saved</h1>
        <p class="success__payout">
            <?= count($saved['items']) === 1 ? '1 item' : e(count($saved['items'])) . ' items' ?>.
            Pay out <strong><?= e(format_price($saved['payout_amount'])) ?></strong>
            <?= $saved['payout_type'] === 'store_credit'
                ? 'in store credit (cash value ' . e(format_price($saved['cash_amount'])) . ').'
                : 'in cash.' ?>
        </p>
        <ul class="saved-items">
            <?php foreach ($saved['items'] as $item): ?>
                <li class="saved-items__item">
                    <img src="<?= e($item['photo_url']) ?>" alt="<?= e($item['title']) ?>" width="64" height="80">
                    <span class="saved-items__text">
                        <strong><?= e($item['title']) ?></strong>
                        <span>Size <?= e($item['size']) ?> · <?= e(format_price($item['sale_price'])) ?></span>
                    </span>
                    <span class="shelf-badge" aria-label="Shelf <?= e($item['shelf_code']) ?>"><?= e($item['shelf_code']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="button" href="/staff/intake.php">Start a new buy-in</a>
    </section>
<?php else: ?>

<h1 class="page__title">New buy-in</h1>
<p class="page__intro">Register the clothes bought from a walk-in seller. They appear in the archive as soon as you save.</p>

<?php if ($formError !== null): ?>
    <p class="notice notice--error" role="alert"><?= e($formError) ?></p>
<?php endif; ?>

<?php if ($summary !== []): ?>
    <div class="error-summary" role="alert" tabindex="-1" id="error-summary">
        <h2 class="error-summary__title">Nothing was saved yet. Please fix <?= count($summary) === 1 ? 'this problem' : 'these ' . count($summary) . ' problems' ?>:</h2>
        <ul>
            <?php foreach ($summary as [$href, $message]): ?>
                <li><a href="<?= e($href) ?>"><?= e($message) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form class="intake" id="intake-form" method="post" action="/staff/intake.php" enctype="multipart/form-data" novalidate
      data-suggestions="<?= e(json_encode($suggestions)) ?>" data-max-items="<?= Intake::MAX_ITEMS ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div id="items" class="items">
        <?php foreach (array_values($rows) as $i => $row) {
            render_intake_item($i, $i + 1, $row + $blankRow, $errors['rows'][$i] ?? [], $categories);
        } ?>
    </div>

    <button type="button" class="button button--secondary" id="add-item">Add item</button>
    <p class="field__hint" id="max-items-note" hidden>A buy-in can have at most <?= Intake::MAX_ITEMS ?> items.</p>

    <fieldset class="payout<?= isset($errors['payout_type']) ? ' field--invalid' : '' ?>">
        <legend>Payout</legend>
        <div class="radio-group">
            <?php foreach (Intake::PAYOUT_TYPES as $value => $label): ?>
                <label class="radio" for="payout-<?= e(str_replace('_', '-', $value)) ?>">
                    <input type="radio" id="payout-<?= e(str_replace('_', '-', $value)) ?>" name="payout_type" value="<?= e($value) ?>" <?= $payoutType === $value ? 'checked' : '' ?>>
                    <span><?= e($label) ?><?= $value === 'store_credit' ? ' (cash × 1.2)' : '' ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (isset($errors['payout_type'])): ?>
            <p class="field__error"><?= e($errors['payout_type']) ?></p>
        <?php endif; ?>
        <p class="payout__total" aria-live="polite">
            <output id="payout-total" for="intake-form">Enter purchase prices and choose cash or store credit.</output>
        </p>
        <p class="field__hint">The final amount is calculated again when you save.</p>
    </fieldset>

    <button type="submit" class="button button--large">Save buy-in</button>
</form>

<template id="item-template">
    <?php render_intake_item('__KEY__', '__NUM__', $blankRow, [], $categories); ?>
</template>

<?php endif; ?>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
