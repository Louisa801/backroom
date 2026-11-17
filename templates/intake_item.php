<?php
/**
 * One item block of the buy-in form.
 * Also rendered once with $key = '__KEY__' inside <template> for intake.js.
 *
 * @var string|int $key       form key and id part
 * @var int|string $number    shown as "Item N"
 * @var array      $row       field values (+ photo_id)
 * @var array      $rowErrors field => message
 * @var list<array{id: int, name: string}> $categories
 */
$id = static fn (string $field): string => "item-$key-$field";
$name = static fn (string $field): string => "items[$key][$field]";
$err = static function (string $field) use ($rowErrors, $id): string {
    if (!isset($rowErrors[$field])) {
        return '';
    }
    return '<p class="field__error" id="' . e($id($field)) . '-error">' . e($rowErrors[$field]) . '</p>';
};
$attrs = static function (string $field) use ($rowErrors, $id): string {
    return isset($rowErrors[$field]) ? ' aria-invalid="true" aria-describedby="' . e($id($field)) . '-error"' : '';
};
$selected = static fn (string $field, string|int $value): string => (string) ($row[$field] ?? '') === (string) $value ? ' selected' : '';
?>
<fieldset class="item-block" data-item>
    <legend class="item-block__legend">Item <span data-item-number><?= e($number) ?></span></legend>

    <div class="item-block__grid">
        <div class="field field--wide<?= isset($rowErrors['photo']) ? ' field--invalid' : '' ?>">
            <label for="<?= e($id('photo')) ?>">Photo</label>
            <?php if (!empty($row['photo_id'])): ?>
                <div class="photo-kept">
                    <img src="/photo.php?id=<?= e($row['photo_id']) ?>" alt="Photo kept for <?= e($row['title'] !== '' ? $row['title'] : 'this item') ?>" width="96" height="120">
                    <p>Photo kept. Choose a new file only to replace it.</p>
                </div>
                <input type="hidden" name="<?= e($name('pending_photo')) ?>" value="<?= e($row['photo_id']) ?>">
            <?php endif; ?>
            <input type="file" id="<?= e($id('photo')) ?>" name="photo[<?= e($key) ?>]" accept="image/jpeg,image/png,image/webp"<?= $attrs('photo') ?>>
            <p class="field__hint">JPEG, PNG or WebP, up to 8 MB.</p>
            <?= $err('photo') ?>
        </div>

        <div class="field field--wide<?= isset($rowErrors['title']) ? ' field--invalid' : '' ?>">
            <label for="<?= e($id('title')) ?>">Title</label>
            <input type="text" id="<?= e($id('title')) ?>" name="<?= e($name('title')) ?>" value="<?= e($row['title'] ?? '') ?>" maxlength="<?= Intake::TITLE_MAX ?>" autocomplete="off"<?= $attrs('title') ?>>
            <?= $err('title') ?>
        </div>

        <div class="field<?= isset($rowErrors['category']) ? ' field--invalid' : '' ?>">
            <label for="<?= e($id('category')) ?>">Category</label>
            <select id="<?= e($id('category')) ?>" name="<?= e($name('category')) ?>"<?= $attrs('category') ?>>
                <option value="">Choose…</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['id']) ?>"<?= $selected('category', $category['id']) ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('category') ?>
        </div>

        <div class="field<?= isset($rowErrors['condition']) ? ' field--invalid' : '' ?>">
            <label for="<?= e($id('condition')) ?>">Condition</label>
            <select id="<?= e($id('condition')) ?>" name="<?= e($name('condition')) ?>"<?= $attrs('condition') ?>>
                <option value="">Choose…</option>
                <?php foreach (Intake::CONDITIONS as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $selected('condition', $value) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('condition') ?>
        </div>

        <div class="field<?= isset($rowErrors['size']) ? ' field--invalid' : '' ?>">
            <label for="<?= e($id('size')) ?>">Size</label>
            <select id="<?= e($id('size')) ?>" name="<?= e($name('size')) ?>"<?= $attrs('size') ?>>
                <option value="">Choose…</option>
                <?php foreach (Items::SIZES as $size): ?>
                    <option value="<?= e($size) ?>"<?= $selected('size', $size) ?>><?= e($size) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('size') ?>
        </div>

        <div class="field<?= isset($rowErrors['colour']) ? ' field--invalid' : '' ?>">
            <label for="<?= e($id('colour')) ?>">Colour</label>
            <select id="<?= e($id('colour')) ?>" name="<?= e($name('colour')) ?>"<?= $attrs('colour') ?>>
                <option value="">Choose…</option>
                <?php foreach (Items::COLOURS as $colour): ?>
                    <option value="<?= e($colour) ?>"<?= $selected('colour', $colour) ?>><?= e(ucfirst($colour)) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('colour') ?>
        </div>

        <div class="field<?= isset($rowErrors['purchase_price']) ? ' field--invalid' : '' ?>">
            <label for="<?= e($id('purchase_price')) ?>">Purchase price (€)</label>
            <input type="text" inputmode="decimal" id="<?= e($id('purchase_price')) ?>" name="<?= e($name('purchase_price')) ?>" value="<?= e($row['purchase_price'] ?? '') ?>" placeholder="0.00" autocomplete="off" data-purchase<?= $attrs('purchase_price') ?>>
            <p class="field__hint">What the shop pays.</p>
            <?= $err('purchase_price') ?>
        </div>

        <div class="field<?= isset($rowErrors['sale_price']) ? ' field--invalid' : '' ?>">
            <label for="<?= e($id('sale_price')) ?>">Sale price (€)</label>
            <input type="text" inputmode="decimal" id="<?= e($id('sale_price')) ?>" name="<?= e($name('sale_price')) ?>" value="<?= e($row['sale_price'] ?? '') ?>" placeholder="0.00" autocomplete="off"<?= $attrs('sale_price') ?>>
            <?= $err('sale_price') ?>
        </div>

        <div class="field<?= isset($rowErrors['shelf_code']) ? ' field--invalid' : '' ?>">
            <label for="<?= e($id('shelf_code')) ?>">Shelf code</label>
            <input type="text" id="<?= e($id('shelf_code')) ?>" name="<?= e($name('shelf_code')) ?>" value="<?= e($row['shelf_code'] ?? '') ?>" maxlength="4" autocapitalize="characters" autocomplete="off" data-shelf<?= $attrs('shelf_code') ?>>
            <p class="field__hint">Next free spot suggested. You can change it.</p>
            <?= $err('shelf_code') ?>
        </div>
    </div>

    <button type="button" class="button button--secondary item-block__remove" data-remove-item>Remove item&nbsp;<span data-item-number><?= e($number) ?></span></button>
</fieldset>
