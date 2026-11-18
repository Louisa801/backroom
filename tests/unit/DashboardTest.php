<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * M7 dashboard figures with fixed dates. "Now" is always passed in; the real date is never
 * used. Each test empties the tables inside its transaction (rolled back afterwards), so
 * the seed data does not interfere and every figure is exact.
 */
final class DashboardTest extends TestCase
{
    private const PORTO = 'Europe/Lisbon';

    private PDO $pdo;
    private Dashboard $dashboard;
    private int $staffId;
    private int $categoryId;
    private int $shelf = 0;

    protected function setUp(): void
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $this->pdo = db();
        $this->pdo->beginTransaction();
        foreach (['sales', 'request_items', 'tryon_requests', 'items', 'photos', 'buy_ins'] as $table) {
            $this->pdo->exec("DELETE FROM $table");
        }
        $this->staffId = current_staff_id($this->pdo);
        $this->categoryId = (int) $this->pdo->query('SELECT min(id) FROM categories')->fetchColumn();
        $this->dashboard = new Dashboard($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    // ---------------- helpers ----------------

    private static function porto(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone(self::PORTO));
    }

    private static function at(DateTimeImmutable|string $time): string
    {
        return ($time instanceof DateTimeImmutable ? $time : self::porto($time))->format(DATE_ATOM);
    }

    private function buyIn(string|DateTimeImmutable $at, string $type = 'cash', int $cashCents = 1000): int
    {
        $payout = Intake::payout([$cashCents], $type);
        $stmt = $this->pdo->prepare('INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount, created_at) VALUES (?, ?, ?, ?, ?) RETURNING id');
        $stmt->execute([$this->staffId, $type, Intake::centsToDecimal($payout['cash']), Intake::centsToDecimal($payout['payout']), self::at($at)]);
        return (int) $stmt->fetchColumn();
    }

    private function item(int $buyIn, string $title, string $status = 'in_archive', string $purchase = '10.00', string $sale = '30.00'): int
    {
        $this->shelf++;
        $stmt = $this->pdo->prepare(
            "INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code, status)
             VALUES (?, ?, ?, 'M', 'black', 'good', ?, ?, 'assets/test.svg', ?, ?) RETURNING id"
        );
        $stmt->execute([$buyIn, $this->categoryId, $title, $purchase, $sale, sprintf('%s-%02d', chr(65 + intdiv($this->shelf - 1, 99)), ($this->shelf - 1) % 99 + 1), $status]);
        return (int) $stmt->fetchColumn();
    }

    private function request(
        string|DateTimeImmutable $created,
        string|DateTimeImmutable|null $ready = null,
        string $status = 'new',
        string|DateTimeImmutable|null $closed = null,
    ): int {
        $stmt = $this->pdo->prepare('INSERT INTO tryon_requests (fitting_room, status, created_at, ready_at, closed_at) VALUES (1, ?, ?, ?, ?) RETURNING id');
        $stmt->execute([$status, self::at($created), $ready === null ? null : self::at($ready), $closed === null ? null : self::at($closed)]);
        return (int) $stmt->fetchColumn();
    }

    private function inRequest(int $request, int $item, ?string $outcome = null, string|DateTimeImmutable|null $outcomeAt = null): void
    {
        $this->pdo->prepare('INSERT INTO request_items (request_id, item_id, outcome, outcome_at) VALUES (?, ?, ?, ?)')
            ->execute([$request, $item, $outcome, $outcomeAt === null ? null : self::at($outcomeAt)]);
    }

    /** An item bought from a fitting room at the given moment. */
    private function soldItem(string $title, string|DateTimeImmutable $soldAt, string $purchase = '10.00', string $sale = '30.00'): int
    {
        $soldAt = $soldAt instanceof DateTimeImmutable ? $soldAt : self::porto($soldAt);
        $item = $this->item($this->buyIn('2031-01-01 10:00'), $title, 'sold', $purchase, $sale);
        $request = $this->request($soldAt->modify('-1 hour'), $soldAt->modify('-30 minutes'), 'ready');
        $this->inRequest($request, $item, 'sold', $soldAt);
        $this->pdo->prepare('INSERT INTO sales (item_id, request_id, sold_at, price) VALUES (?, ?, ?, ?)')
            ->execute([$item, $request, self::at($soldAt), $sale]);
        return $item;
    }

    // ---------------- periods ----------------

    public function testWeekStartsOnMondayAndTodayAtMidnightPortoTime(): void
    {
        $now = self::porto('2031-03-12 15:00'); // a Wednesday

        $week = Dashboard::period('week', $now);
        $today = Dashboard::period('today', $now);

        self::assertSame('2031-03-10 00:00 Europe/Lisbon', $week['start']->format('Y-m-d H:i e'));
        self::assertSame('2031-03-12 00:00', $today['start']->format('Y-m-d H:i'));
        self::assertEquals($now, $week['end']);
    }

    public function testOnMondayTheWeekStartsThatDayAndOnSundaySixDaysEarlier(): void
    {
        self::assertSame('2031-03-10 00:00', Dashboard::period('week', self::porto('2031-03-10 09:00'))['start']->format('Y-m-d H:i'));
        self::assertSame('2031-03-10 00:00', Dashboard::period('week', self::porto('2031-03-16 22:00'))['start']->format('Y-m-d H:i'));
    }

    public function testWeekStartStaysAtMidnightAcrossTheSummerTimeChange(): void
    {
        // Summer time starts on Sunday 30 March 2031: Porto goes from UTC+0 to UTC+1.
        $start = Dashboard::period('week', self::porto('2031-03-30 12:00'))['start'];

        self::assertSame('2031-03-24 00:00 +00:00', $start->format('Y-m-d H:i P'));
    }

    public function testNowGivenInUtcIsReadAsPortoTime(): void
    {
        // 23:30 UTC on 1 July is already 00:30 on 2 July in Porto (summer, UTC+1).
        $today = Dashboard::period('today', new DateTimeImmutable('2031-07-01 23:30', new DateTimeZone('UTC')));
        self::assertSame('2031-07-02 00:00 +01:00', $today['start']->format('Y-m-d H:i P'));
    }

    public function testUnknownPeriodFallsBackToTheWeek(): void
    {
        self::assertSame('week', Dashboard::period('year', self::porto('2031-03-12 15:00'))['name']);
    }

    // ---------------- time zone and boundaries ----------------

    private function salesAroundMidnightInSummer(): void
    {
        $utc = new DateTimeZone('UTC');
        $this->soldItem('Just after midnight in Porto', new DateTimeImmutable('2031-07-01 23:45', $utc), sale: '50.00');
        $this->soldItem('Late the day before in Porto', new DateTimeImmutable('2031-07-01 22:59', $utc), sale: '70.00');
    }

    public function testTodayUsesPortoTimeNotUtc(): void
    {
        $this->salesAroundMidnightInSummer();

        // 01:30 in Porto = 00:30 UTC. Today in Porto started at 23:00 UTC the day before.
        $today = $this->dashboard->summary('today', self::porto('2031-07-02 01:30'));

        self::assertSame(1, $today['sold']);
        self::assertSame('50.00', $today['revenue']);
    }

    public function testSameFiguresWhenServerAndDatabaseRunInUtc(): void
    {
        $this->salesAroundMidnightInSummer();
        $phpZone = date_default_timezone_get();
        try {
            date_default_timezone_set('UTC');                  // as on Render
            $this->pdo->exec("SET LOCAL TIME ZONE 'UTC'");     // reset when the test's transaction ends
            $now = new DateTimeImmutable('2031-07-02 00:30');  // 01:30 in Porto, written in UTC

            $today = $this->dashboard->summary('today', $now);

            self::assertSame(1, $today['sold']);
            self::assertSame('50.00', $today['revenue']);
        } finally {
            date_default_timezone_set($phpZone);
        }
    }

    public function testLateSundayIsLastWeekEarlyMondayIsThisWeek(): void
    {
        $this->soldItem('Sunday evening', '2031-03-09 23:55', sale: '20.00');
        $this->soldItem('Monday morning', '2031-03-10 00:05', sale: '30.00');

        $week = $this->dashboard->summary('week', self::porto('2031-03-10 00:10'));

        self::assertSame(1, $week['sold']);
        self::assertSame('30.00', $week['revenue']);
    }

    public function testEventsAfterNowAreNotCounted(): void
    {
        $this->soldItem('Later today', '2031-03-12 18:00');
        self::assertSame(0, $this->dashboard->summary('today', self::porto('2031-03-12 15:00'))['sold']);
    }

    // ---------------- every figure ----------------

    /**
     * Now: Wednesday 12 March 2031, 15:00 in Porto. Week = Mon 10 March 00:00 to now.
     */
    private function weekOfFixtures(): void
    {
        // Buy-ins: store credit this week (Tue) and today (Wed), cash today, store credit last week.
        $this->buyIn('2031-03-11 10:00', 'store_credit', 2500);  // payout 30.00
        $this->buyIn('2031-03-12 11:00', 'store_credit', 500);   // payout  6.00
        $this->buyIn('2031-03-12 09:00', 'cash', 4000);          // cash: not store credit
        $this->buyIn('2031-03-05 10:00', 'store_credit', 1000);  // last week
        $old = $this->buyIn('2031-01-01 10:00');

        $coat = $this->item($old, 'Coat', 'sold', '10.00', '40.00');
        $shirt = $this->item($old, 'Shirt', 'sold', '15.00', '20.00');
        $scarf = $this->item($old, 'Scarf', 'in_archive', '5.00', '12.00');   // tried, back to archive
        $hat = $this->item($old, 'Hat', 'in_fitting_room', '8.00', '20.00');  // tried, undecided
        $jeans = $this->item($old, 'Jeans', 'requested');
        $this->item($old, 'Archive 1');
        $this->item($old, 'Archive 2');

        // Request 1: created and in the fitting room on Tuesday, 4 items.
        $r1 = $this->request('2031-03-11 09:00', '2031-03-11 09:30', 'ready');
        $this->inRequest($r1, $coat, 'sold', '2031-03-11 09:45');
        $this->inRequest($r1, $shirt, 'sold', '2031-03-12 10:00');
        $this->inRequest($r1, $scarf, 'returned', '2031-03-12 10:05');
        $this->inRequest($r1, $hat);
        // Request 2: sent today, not fetched yet.
        $r2 = $this->request('2031-03-12 14:00');
        $this->inRequest($r2, $jeans);

        $sale = $this->pdo->prepare('INSERT INTO sales (item_id, request_id, sold_at, price) VALUES (?, ?, ?, ?)');
        $sale->execute([$coat, $r1, self::at('2031-03-11 09:45'), '40.00']);
        $sale->execute([$shirt, $r1, self::at('2031-03-12 10:00'), '20.00']);
    }

    public function testEveryFigureThisWeek(): void
    {
        $this->weekOfFixtures();

        $week = $this->dashboard->summary('week', self::porto('2031-03-12 15:00'));

        self::assertSame(3, $week['in_archive'], 'Scarf and the two archive items.');
        self::assertSame(2, $week['sold']);
        self::assertSame('60.00', $week['revenue']);
        // Gross margin: (40 + 20 - 10 - 15) / (40 + 20) = 35 / 60.
        self::assertEqualsWithDelta(35 / 60, $week['gross_margin'], 1e-9);
        self::assertSame('58%', format_percent($week['gross_margin']));
        // Funnel: the 5 items requested this week. Jeans on their way, hat undecided in the
        // fitting room, coat and shirt bought, scarf returned.
        self::assertSame(
            ['requested' => 5, 'on_way' => 1, 'tried' => 4, 'in_room' => 1, 'decided' => 3, 'bought' => 2, 'returned' => 1],
            $week['funnel']
        );
        self::assertEqualsWithDelta(2 / 3, $week['conversion'], 1e-9, 'Buy rate: 2 bought of 3 decided.');
        self::assertEqualsWithDelta(4 / 5, $week['tried_share'], 1e-9);
        self::assertSame('36.00', $week['store_credit'], 'Store credit this week only, cash not included.');
    }

    public function testEveryFigureToday(): void
    {
        $this->weekOfFixtures();

        $today = $this->dashboard->summary('today', self::porto('2031-03-12 15:00'));

        self::assertSame(3, $today['in_archive'], 'In the archive is a snapshot, the same for both periods.');
        self::assertSame(1, $today['sold']);
        self::assertSame('20.00', $today['revenue']);
        self::assertEqualsWithDelta(0.25, $today['gross_margin'], 1e-9);
        // Only the jeans were requested today, and they're still on their way. The shirt was
        // sold today but requested on Tuesday, so it counts in Sold, not in today's funnel.
        self::assertSame(
            ['requested' => 1, 'on_way' => 1, 'tried' => 0, 'in_room' => 0, 'decided' => 0, 'bought' => 0, 'returned' => 0],
            $today['funnel']
        );
        self::assertNull($today['conversion'], 'Nothing requested today has been decided yet.');
        self::assertSame(0.0, $today['tried_share']);
        self::assertSame('6.00', $today['store_credit']);
    }

    public function testConversionIsSoldDividedByItemsWithAnOutcome(): void
    {
        $old = $this->buyIn('2031-01-01 10:00');
        $request = $this->request('2031-03-12 09:00', '2031-03-12 09:30', 'ready');
        $this->inRequest($request, $this->item($old, 'Sold', 'sold'), 'sold', '2031-03-12 10:00');
        $this->inRequest($request, $this->item($old, 'Returned 1'), 'returned', '2031-03-12 10:01');
        $this->inRequest($request, $this->item($old, 'Returned 2'), 'returned', '2031-03-12 10:02');
        $this->inRequest($request, $this->item($old, 'Undecided', 'in_fitting_room'));

        $today = $this->dashboard->summary('today', self::porto('2031-03-12 15:00'));

        self::assertEqualsWithDelta(1 / 3, $today['conversion'], 1e-9);
        self::assertSame('33%', format_percent($today['conversion']));
    }

    public function testFunnelAlwaysAddsUp(): void
    {
        $this->weekOfFixtures();
        // Extra: a request still being fetched and one fully decided, both this week.
        $old = $this->buyIn('2031-01-01 10:00');
        $fetching = $this->request('2031-03-11 12:00', null, 'fetching');
        $this->inRequest($fetching, $this->item($old, 'Being fetched 1', 'requested'));
        $this->inRequest($fetching, $this->item($old, 'Being fetched 2', 'requested'));
        $done = $this->request('2031-03-10 08:00', '2031-03-10 08:30', 'closed', '2031-03-10 09:00');
        $this->inRequest($done, $this->item($old, 'Returned Monday'), 'returned', '2031-03-10 08:50');

        foreach (['today', 'week'] as $period) {
            $f = $this->dashboard->summary($period, self::porto('2031-03-12 15:00'))['funnel'];
            self::assertSame($f['requested'], $f['on_way'] + $f['in_room'] + $f['bought'] + $f['returned'], "$period: every requested item is in one place");
            self::assertSame($f['tried'], $f['requested'] - $f['on_way'], "$period: tried = requested - on their way");
            self::assertSame($f['decided'], $f['tried'] - $f['in_room'], "$period: decided = tried - still in a fitting room");
            self::assertSame($f['decided'], $f['bought'] + $f['returned']);
        }
        $week = $this->dashboard->summary('week', self::porto('2031-03-12 15:00'))['funnel'];
        self::assertSame(['requested' => 8, 'on_way' => 3, 'tried' => 5, 'in_room' => 1, 'decided' => 4, 'bought' => 2, 'returned' => 2], $week);
    }

    public function testARequestFromLastWeekStaysInLastWeeksFunnel(): void
    {
        // Requested on Sunday 23:50, tried and bought on Monday morning.
        $item = $this->item($this->buyIn('2031-01-01 10:00'), 'Sunday request', 'sold', '10.00', '30.00');
        $request = $this->request('2031-03-09 23:50', '2031-03-10 00:20', 'closed', '2031-03-10 00:40');
        $this->inRequest($request, $item, 'sold', '2031-03-10 00:40');
        $this->pdo->prepare('INSERT INTO sales (item_id, request_id, sold_at, price) VALUES (?, ?, ?, ?)')
            ->execute([$item, $request, self::at('2031-03-10 00:40'), '30.00']);

        $thisWeek = $this->dashboard->summary('week', self::porto('2031-03-12 15:00'));
        $lastWeek = $this->dashboard->summary('week', self::porto('2031-03-09 23:59'));

        self::assertSame(1, $thisWeek['sold'], 'Sold counts by sale date: Monday.');
        self::assertSame(0, $thisWeek['funnel']['requested'], 'The funnel counts by request date: last week.');
        self::assertNull($thisWeek['conversion']);
        self::assertSame(1, $lastWeek['funnel']['requested'], 'Seen from Sunday night, it is on its way.');
        self::assertSame(1, $lastWeek['funnel']['bought'], 'Its place is where it is now: bought.');
    }

    public function testTriedButNothingDecidedGivesNoBuyRate(): void
    {
        $request = $this->request('2031-03-12 09:00', '2031-03-12 09:30', 'ready');
        $this->inRequest($request, $this->item($this->buyIn('2031-01-01 10:00'), 'Undecided', 'in_fitting_room'));

        $today = $this->dashboard->summary('today', self::porto('2031-03-12 15:00'));

        self::assertSame(1, $today['funnel']['in_room']);
        self::assertNull($today['conversion']);
        self::assertSame('–', format_percent($today['conversion']));
    }

    // ---------------- empty ----------------

    public function testEmptyShopGivesZerosAndDashesNotErrors(): void
    {
        foreach (['today', 'week'] as $period) {
            $summary = $this->dashboard->summary($period, self::porto('2031-03-12 15:00'));

            self::assertSame(0, $summary['in_archive']);
            self::assertSame(0, $summary['sold']);
            self::assertSame('0.00', $summary['revenue']);
            self::assertNull($summary['gross_margin']);
            self::assertNull($summary['conversion']);
            self::assertSame('–', format_percent($summary['gross_margin']));
            self::assertSame(['requested' => 0, 'on_way' => 0, 'tried' => 0, 'in_room' => 0, 'decided' => 0, 'bought' => 0, 'returned' => 0], $summary['funnel']);
            self::assertNull($summary['tried_share']);
            self::assertSame('0.00', $summary['store_credit']);
        }
        self::assertSame([], $this->dashboard->slowMovers(self::porto('2031-03-12 15:00')));
    }

    public function testOutcomesWithoutSalesStillGiveAConversion(): void
    {
        $request = $this->request('2031-03-12 09:00', '2031-03-12 09:30', 'ready');
        $this->inRequest($request, $this->item($this->buyIn('2031-01-01 10:00'), 'Returned'), 'returned', '2031-03-12 10:00');

        $today = $this->dashboard->summary('today', self::porto('2031-03-12 15:00'));

        self::assertSame(0.0, $today['conversion']);
        self::assertNull($today['gross_margin'], 'No sales: no margin.');
    }

    // ---------------- slow movers ----------------

    public function testSlowMoversStartAfter60CalendarDaysOldestFirst(): void
    {
        $now = self::porto('2031-03-12 15:00');
        $this->item($this->buyIn('2031-01-11 00:01'), 'Exactly 60 days');            // not slow
        $this->item($this->buyIn('2031-01-10 23:59'), 'Sixty-one days', sale: '25.00'); // slow
        $this->item($this->buyIn('2030-12-01 12:00'), 'Hundred and one days');       // slow
        $this->item($this->buyIn('2030-11-01 12:00'), 'Old but sold', 'sold');
        $this->item($this->buyIn('2030-11-01 12:00'), 'Old but requested', 'requested');

        $slow = $this->dashboard->slowMovers($now);

        self::assertSame(['Hundred and one days', 'Sixty-one days'], array_column($slow, 'title'));
        self::assertSame([101, 61], array_column($slow, 'days'));
        self::assertSame('25.00', $slow[1]['sale_price']);
        self::assertMatchesRegularExpression('/^[A-Z]-\d{2}$/', $slow[0]['shelf_code']);
    }

    public function testSlowMoverDaysCountInPortoTime(): void
    {
        // 23:30 UTC on 1 May is already 00:30 on 2 May in Porto (summer, UTC+1).
        // Counting with the UTC date (1 May) would make it a slow mover one day too early.
        $this->item($this->buyIn(new DateTimeImmutable('2031-05-01 23:30', new DateTimeZone('UTC'))), 'Bought in on 2 May Porto time');

        // 2 May + 61 days = 2 July (Porto). On 1 July Porto time it is only 60 days.
        self::assertSame([], $this->dashboard->slowMovers(self::porto('2031-07-01 23:00')));
        self::assertSame([61], array_column($this->dashboard->slowMovers(self::porto('2031-07-02 08:00')), 'days'));
    }
}
