<?php
declare(strict_types=1);

// M7: manager dashboard, after docs/mockups/4-dashboard.png. (Login is added in the login story.)

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

$funnel = $summary['funnel'];
$funnelMax = max(1, ...array_values($funnel));
$funnelRows = [
    ['Requested', 'items customers asked to try on', $funnel['requested'], false],
    ['Tried', 'items brought to a fitting room', $funnel['tried'], false],
    ['Bought', 'items sold', $funnel['bought'], true],
];

$pageTitle = 'Dashboard';
$headerNote = 'Manager';
require __DIR__ . '/../../templates/header.php';
?>
<div class="dashboard">
    <div class="dashboard__head">
        <div>
            <h1 class="dashboard__title">Backroom · <?= e($when) ?></h1>
            <p class="dashboard__range"><?= e($range) ?> (Porto time)</p>
        </div>
        <nav class="segmented" aria-label="Period">
            <a href="?period=today"<?= $isToday ? ' aria-current="page"' : '' ?>>Today</a>
            <a href="?period=week"<?= !$isToday ? ' aria-current="page"' : '' ?>>This week</a>
        </nav>
    </div>

    <ul class="tiles">
        <li class="tile">
            <span class="tile__label">In the archive now</span>
            <strong class="tile__value"><?= e(number_format($summary['in_archive'])) ?></strong>
            <span class="tile__note">items customers can see on the tablet</span>
        </li>
        <li class="tile">
            <span class="tile__label">Sold</span>
            <strong class="tile__value"><?= e(number_format($summary['sold'])) ?></strong>
            <span class="tile__note">items sold <?= e($when) ?></span>
        </li>
        <li class="tile">
            <span class="tile__label">Revenue</span>
            <strong class="tile__value"><?= e(format_price($summary['revenue'])) ?></strong>
            <span class="tile__note">total of the sale prices <?= e($when) ?></span>
        </li>
        <li class="tile">
            <span class="tile__label">Gross margin</span>
            <strong class="tile__value"><?= e(format_percent($summary['gross_margin'])) ?></strong>
            <span class="tile__note"><?= $summary['gross_margin'] === null
                ? 'no sales ' . e($when) . ' yet'
                : 'of every euro taken in, how much is profit' ?></span>
        </li>
        <li class="tile">
            <span class="tile__label">Try-on → buy</span>
            <strong class="tile__value tile__value--good"><?= e(format_percent($summary['conversion'])) ?></strong>
            <span class="tile__note"><?= $summary['conversion'] === null
                ? 'no fitting-room decisions ' . e($when) . ' yet'
                : e($summary['outcomes_sold']) . ' of ' . e($summary['outcomes_total']) . ' items tried on were bought' ?></span>
        </li>
    </ul>

    <div class="dashboard__panels">
        <section class="panel" aria-labelledby="funnel-title">
            <h2 class="panel__title" id="funnel-title">Item funnel</h2>
            <p class="panel__note">What happened to items <?= e($when) ?>, from request to sale.</p>
            <?php if ($funnel['requested'] + $funnel['tried'] + $funnel['bought'] === 0): ?>
                <p class="panel__empty">No try-ons <?= e($when) ?> yet.</p>
            <?php else: ?>
                <dl class="funnel">
                    <?php foreach ($funnelRows as [$label, $explain, $count, $accent]): ?>
                        <div class="funnel__row">
                            <dt><?= e($label) ?> <span class="funnel__explain"><?= e($explain) ?></span></dt>
                            <dd>
                                <span class="funnel__bar<?= $accent ? ' funnel__bar--accent' : '' ?>"
                                      style="width: <?= $count === 0 ? 0 : max(2, (int) round($count / $funnelMax * 100)) ?>%" aria-hidden="true"></span>
                                <span class="funnel__count"><?= e(number_format($count)) ?></span>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>

        <section class="panel" aria-labelledby="slow-title">
            <h2 class="panel__title" id="slow-title">Slow movers (<?= Dashboard::SLOW_MOVER_DAYS ?>+ days)</h2>
            <p class="panel__note">Items in the archive for more than <?= Dashboard::SLOW_MOVER_DAYS ?> days since they were bought in, oldest first.</p>
            <?php if ($slowMovers === []): ?>
                <p class="panel__empty">No slow movers. Everything in the archive was bought in during the last <?= Dashboard::SLOW_MOVER_DAYS ?> days.</p>
            <?php else: ?>
                <ul class="slow-list">
                    <?php foreach ($slowMovers as $item): ?>
                        <li>
                            <span class="slow-list__name"><strong><?= e($item['title']) ?></strong> · <?= e($item['size']) ?> · <?= e($item['days']) ?> d</span>
                            <span class="slow-list__meta">Shelf <span class="shelf"><?= e($item['shelf_code']) ?></span> · <?= e(format_price($item['sale_price'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <p class="dashboard__footer">
        Store credit given out <?= e($when) ?>: <strong><?= e(format_price($summary['store_credit'])) ?></strong>
        <span class="dashboard__footer-note">(buy-ins paid in store credit, including the × 1.2 bonus)</span>
    </p>
</div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
