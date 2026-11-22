<?php
/**
 * Staff board: fetch queue (M4) and fitting rooms (M6), after docs/mockups/3-staff.png.
 * Rendered by public/staff/index.php and, for the 10-second refresh, by public/staff/board.php.
 *
 * @var list<array> $queue  Requests::queue()
 * @var array<int, list<array>> $rooms  Requests::fittingRooms()
 * @var string $csrf
 */
$waiting = 'format_waiting'; // src/helpers.php: "waiting 2 h 23 min"
$hidden = static fn (string $name, string|int $value): string =>
    '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
$version = md5(json_encode([$queue, $rooms]));
?>
<div class="board" id="board" data-version="<?= e($version) ?>">
    <section class="board__col" id="queue" aria-labelledby="queue-title">
        <h2 class="board__title" id="queue-title">Fetch queue · <?= count($queue) ?></h2>

        <?php if ($queue === []): ?>
            <p class="board__empty">No requests waiting. New requests appear here by themselves.</p>
        <?php endif; ?>

        <?php foreach ($queue as $request): ?>
            <article class="queue-card" aria-label="Request <?= e($request['id']) ?>">
                <p class="queue-card__meta">Request #<?= e($request['id']) ?> · <?= e($waiting($request['waiting_minutes'])) ?></p>
                <ul class="queue-card__items">
                    <?php foreach ($request['items'] as $item): ?>
                        <li>
                            <strong><?= e($item['title']) ?> · <?= e($item['size']) ?></strong>
                            <span>Shelf <span class="shelf"><?= e($item['shelf_code']) ?></span> → Fitting room <?= e($request['fitting_room']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="queue-card__footer">
                    <?php if ($request['status'] === 'new'): ?>
                        <span class="pill pill--new">New request</span>
                    <?php else: ?>
                        <span class="pill pill--fetching">Fetching</span>
                    <?php endif; ?>
                    <form method="post" action="/staff/action.php" data-staff-action>
                        <?= $hidden('csrf_token', $csrf) ?>
                        <?= $hidden('action', 'advance') ?>
                        <?= $hidden('request_id', $request['id']) ?>
                        <?= $hidden('from', $request['status']) ?>
                        <button type="submit" class="button">
                            <?= $request['status'] === 'new' ? 'Start fetching' : 'Ready in fitting room ' . e($request['fitting_room']) ?>
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="board__col" id="rooms" aria-labelledby="rooms-title">
        <h2 class="board__title" id="rooms-title">Fitting rooms</h2>

        <?php foreach ($rooms as $room => $requests): ?>
            <article class="room-card<?= $requests === [] ? ' room-card--free' : '' ?>" id="room-<?= e($room) ?>" aria-labelledby="room-<?= e($room) ?>-title">
                <div class="room-card__head">
                    <h3 class="room-card__title" id="room-<?= e($room) ?>-title">Fitting room <?= e($room) ?></h3>
                    <?php if ($requests === []): ?>
                        <span class="pill pill--free">Free</span>
                    <?php else: ?>
                        <span class="pill pill--ready">In fitting room</span>
                    <?php endif; ?>
                </div>

                <?php foreach ($requests as $request): ?>
                    <p class="room-card__meta">Request #<?= e($request['id']) ?></p>
                    <ul class="room-items">
                        <?php foreach ($request['items'] as $item): ?>
                            <li class="room-item<?= $item['outcome'] !== null ? ' room-item--done' : '' ?>">
                                <img src="<?= e($item['photo_url']) ?>" alt="<?= e($item['title']) ?>" width="48" height="60" loading="lazy">
                                <div class="room-item__text">
                                    <strong><?= e($item['title']) ?> · <?= e($item['size']) ?></strong>
                                    <span>Shelf <span class="shelf"><?= e($item['shelf_code']) ?></span> · <?= e(format_price($item['sale_price'])) ?></span>
                                </div>
                                <?php if ($item['outcome'] === null): ?>
                                    <div class="room-item__actions">
                                        <form method="post" action="/staff/action.php" data-staff-action>
                                            <?= $hidden('csrf_token', $csrf) ?>
                                            <?= $hidden('action', 'sell') ?>
                                            <?= $hidden('request_id', $request['id']) ?>
                                            <?= $hidden('item_id', $item['id']) ?>
                                            <button type="submit" class="button" aria-label="Sold: <?= e($item['title']) ?>">Sold</button>
                                        </form>
                                        <form method="post" action="/staff/action.php" data-staff-action>
                                            <?= $hidden('csrf_token', $csrf) ?>
                                            <?= $hidden('action', 'return') ?>
                                            <?= $hidden('request_id', $request['id']) ?>
                                            <?= $hidden('item_id', $item['id']) ?>
                                            <button type="submit" class="button button--secondary" aria-label="Back to archive: <?= e($item['title']) ?>">Back to archive</button>
                                        </form>
                                    </div>
                                <?php elseif ($item['outcome'] === 'sold'): ?>
                                    <span class="outcome outcome--sold">Sold</span>
                                <?php else: ?>
                                    <span class="outcome">Back in archive (<?= e($item['shelf_code']) ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </section>
</div>
