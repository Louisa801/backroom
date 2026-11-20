<?php
declare(strict_types=1);

// M7: manager dashboard (redesigned after the hand check; see docs/UI_BACKLOG.md).
// (Login is added in the login story.)

require_once __DIR__ . '/../../src/bootstrap.php';

$periodName = is_string($_GET['period'] ?? null) ? $_GET['period'] : 'week';
$now = new DateTimeImmutable('now', new DateTimeZone(Dashboard::TIMEZONE));

$dashboard = new Dashboard(db());
$summary = $dashboard->summary($periodName, $now);
$slowMovers = $dashboard->slowMovers($now);

$period = $summary['period'];
$isToday = $period['name'] === 'today';
$when = $isToday ? 'today' : 'this week';
$range = $isToday
    ? $period['start']->format('D j M') . ', 00:00 – ' . $period['end']->format('H:i')
    : $period['start']->format('D j M') . ' 00:00 – ' . $period['end']->format('D j M H:i');

$f = $summary['funnel'];
$share = static fn (int $part): string => $f['requested'] > 0 ? round($part / $f['requested'] * 100, 2) . '%' : '0%';
// Where every requested item is now. Same order in the bar and the table; together they make "requested".
$places = [
    ['bought',   'Bought',                                    $f['bought']],
    ['returned', 'Returned to the archive',                   $f['returned']],
    ['room',     'Still in a fitting room, not decided yet',  $f['in_room']],
    ['way',      'On their way (requested, not in a fitting room yet)', $f['on_way']],
];
$items = static fn (int $n): string => $n === 1 ? '1 item' : number_format($n) . ' items';

$pageTitle = 'Dashboard';
$headerNote = 'Manager';
require __DIR__ . '/../../templates/header.php';
?>
<div class="dashboard">
    <div class="dashboard__head">
        <div>
            <h1 class="dashboard__title"><?= $isToday ? 'Today' : 'This week' ?></h1>
            <p class="dashboard__range"><?= e($range) ?> · Porto time</p>
        </div>
        <nav class="segmented" aria-label="Period">
            <a href="?period=today"<?= $isToday ? ' aria-current="page"' : '' ?>>Today</a>
            <a href="?period=week"<?= !$isToday ? ' aria-current="page"' : '' ?>>This week</a>
        </nav>
    </div>

    <section class="headline" aria-label="Main figures">
        <ul class="tiles">
            <li class="tile">
                <span class="tile__label">Sold</span>
                <strong class="tile__value"><?= e(number_format($summary['sold'])) ?></strong>
                <span class="tile__note">items sold <?= e($when) ?></span>
            </li>
            <li class="tile">
                <span class="tile__label">Revenue</span>
                <strong class="tile__value"><?= e(format_whole_euros($summary['revenue'])) ?></strong>
                <span class="tile__note">total of sale prices <?= e($when) ?></span>
            </li>
            <li class="tile">
                <span class="tile__label">Gross margin</span>
                <strong class="tile__value"><?= e(format_percent($summary['gross_margin'])) ?></strong>
                <span class="tile__note"><?= $summary['gross_margin'] === null
                    ? 'no sales ' . e($when) . ' yet'
                    : 'of every euro taken in, how much is profit' ?></span>
            </li>
        </ul>
        <p class="quiet-line">
            <?= e($items($summary['in_archive'])) ?> in the archive
            · <?= e(format_price($summary['store_credit'])) ?> given as store credit <?= e($when) ?>
        </p>
    </section>

    <div class="dashboard__panels">
        <section class="panel" aria-labelledby="tryons-title">
            <h2 class="panel__title" id="tryons-title">Try-ons</h2>
            <?php if ($f['requested'] === 0): ?>
                <p class="panel__empty">No try-on requests <?= e($when) ?> yet.</p>
            <?php else: ?>
                <p class="panel__note">Of the <?= e($items($f['requested'])) ?> requested <?= e($when) ?>, where each one is now.</p>

                <p class="steps-line">
                    Requested <strong><?= e($f['requested']) ?></strong>
                    <span aria-hidden="true">→</span><span class="visually-hidden">, then</span>
                    Decided <strong><?= e($f['decided']) ?></strong> (<?= e(format_percent($summary['decided_share'])) ?>)
                    <span aria-hidden="true">→</span><span class="visually-hidden">, then</span>
                    Bought <strong><?= e($f['bought']) ?></strong> (<?= e(format_percent($summary['conversion'])) ?>)
                </p>

                <div class="stack" aria-hidden="true">
                    <?php foreach ($places as [$key, $label, $count]): ?>
                        <?php if ($count > 0): ?>
                            <span class="stack__part stack__part--<?= e($key) ?>" style="width: <?= e($share($count)) ?>"></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <table class="places">
                    <caption class="visually-hidden">Where the requested items are now</caption>
                    <tbody>
                        <?php foreach ($places as [$key, $label, $count]): ?>
                            <tr>
                                <th scope="row"><span class="swatch swatch--<?= e($key) ?>" aria-hidden="true"></span><?= e($label) ?></th>
                                <td><?= e(number_format($count)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Total requested</th>
                            <td><?= e(number_format($f['requested'])) ?></td>
                        </tr>
                    </tfoot>
                </table>

                <p class="panel__fine">
                    Buy rate = bought ÷ decided (bought or returned)<?= $f['decided'] > 0
                        ? ' = ' . e($f['bought']) . ' ÷ ' . e($f['decided']) . ' = ' . e(format_percent($summary['conversion']))
                        : '' ?>.
                    Items on their way or still in a fitting room count once they're decided.
                </p>
            <?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="slow-title">
            <h2 class="panel__title" id="slow-title">Slow movers</h2>
            <p class="panel__note">In the archive for more than <?= Dashboard::SLOW_MOVER_DAYS ?> days since buy-in, oldest first.</p>
            <?php if ($slowMovers === []): ?>
                <p class="panel__empty">No slow movers. Everything in the archive was bought in during the last <?= Dashboard::SLOW_MOVER_DAYS ?> days.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="slow-table">
                        <caption class="visually-hidden">Slow movers, oldest first</caption>
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col">Size</th>
                                <th scope="col">Shelf</th>
                                <th scope="col" class="num">Days</th>
                                <th scope="col" class="num">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slowMovers as $item): ?>
                                <tr>
                                    <td><?= e($item['title']) ?></td>
                                    <td><?= e($item['size']) ?></td>
                                    <td><span class="shelf"><?= e($item['shelf_code']) ?></span></td>
                                    <td class="num"><?= e($item['days']) ?></td>
                                    <td class="num"><?= e(format_price($item['sale_price'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
