<?php
/**
 * @var array{id: int, title: string, size: string, sale_price: string, photo_url: string} $item
 * @var string $filterQuery optional, current filters ("size=M&colour=navy") so the detail page can link back to them
 */
// Keep in sync with <template id="card-template"> in public/index.php and renderCard() in archive.js.
$itemUrl = '/item.php?id=' . $item['id'] . (($filterQuery ?? '') !== '' ? '&' . $filterQuery : '');
?>
<li class="card">
    <a class="card__photo-link" href="<?= e($itemUrl) ?>" tabindex="-1" aria-hidden="true">
        <img class="card__photo" src="<?= e($item['photo_url']) ?>" alt="<?= e($item['title']) ?>" width="400" height="500" loading="lazy">
    </a>
    <div class="card__body">
        <h2 class="card__title"><a class="card__link" href="<?= e($itemUrl) ?>"><?= e($item['title']) ?></a></h2>
        <p class="card__meta">
            <span class="card__size">Size <?= e($item['size']) ?></span>
            <span class="card__price"><?= e(format_price($item['sale_price'])) ?></span>
        </p>
        <button type="button" class="button button--small card__tryon" data-tryon aria-pressed="false"
                data-id="<?= e($item['id']) ?>" data-title="<?= e($item['title']) ?>"
                data-size="<?= e($item['size']) ?>" data-photo="<?= e($item['photo_url']) ?>">Try this on</button>
    </div>
</li>
