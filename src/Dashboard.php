<?php
declare(strict_types=1);

/**
 * M7: figures for the manager dashboard.
 *
 * Shop time is Porto (Europe/Lisbon). "Today" and "this week" are worked out here from a
 * given "now" and passed to SQL as exact timestamps, so the server's time zone (UTC on
 * Render) never matters. Undefined figures (no sales, no outcomes) are null, never a
 * division by zero.
 */
final class Dashboard
{
    public const TIMEZONE = 'Europe/Lisbon';
    public const SLOW_MOVER_DAYS = 60;
    public const PERIODS = ['today', 'week'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * The period "today" (00:00 Porto to now) or "week" (Monday 00:00 Porto to now).
     * Unknown names fall back to "week".
     *
     * @return array{name: string, start: DateTimeImmutable, end: DateTimeImmutable}
     */
    public static function period(string $name, DateTimeImmutable $now): array
    {
        $local = $now->setTimezone(new DateTimeZone(self::TIMEZONE));
        $name = in_array($name, self::PERIODS, true) ? $name : 'week';

        $startOfDay = $local->setTime(0, 0);
        // Going back whole days keeps 00:00 on the wall clock, also across a summer-time change.
        $start = $name === 'today'
            ? $startOfDay
            : $startOfDay->modify('-' . ((int) $local->format('N') - 1) . ' days');

        return ['name' => $name, 'start' => $start, 'end' => $local];
    }

    /**
     * Every figure for one period.
     *
     * Sold, revenue and gross margin count sales by sale date. The funnel and the buy rate
     * follow the items requested in the period to where they are now.
     *
     * @return array{
     *   period: array, in_archive: int, sold: int, revenue: string, gross_margin: ?float,
     *   conversion: ?float, tried_share: ?float,
     *   funnel: array{requested: int, on_way: int, tried: int, in_room: int, decided: int, bought: int, returned: int},
     *   store_credit: string
     * }
     */
    public function summary(string $periodName, DateTimeImmutable $now): array
    {
        $period = self::period($periodName, $now);
        $range = ['start' => $period['start']->format(DATE_ATOM), 'end' => $period['end']->format(DATE_ATOM)];

        $sales = $this->row(
            'SELECT count(*) AS sold,
                    coalesce(sum(s.price), 0)::numeric(12,2) AS revenue,
                    coalesce(sum(i.purchase_price), 0)::numeric(12,2) AS cost
             FROM sales s JOIN items i ON i.id = s.item_id
             WHERE s.sold_at BETWEEN CAST(:start AS timestamptz) AND CAST(:end AS timestamptz)',
            $range
        );

        // Funnel: the items requested in the period, each counted once by where it is now,
        // so the steps always add up (requested = on_way + in_room + bought + returned).
        $where = $this->row(
            "SELECT count(*) FILTER (WHERE r.status IN ('new', 'fetching'))                  AS on_way,
                    count(*) FILTER (WHERE r.status IN ('ready', 'closed') AND ri.outcome IS NULL) AS in_room,
                    count(*) FILTER (WHERE ri.outcome = 'sold')                               AS bought,
                    count(*) FILTER (WHERE ri.outcome = 'returned')                           AS returned
             FROM request_items ri JOIN tryon_requests r ON r.id = ri.request_id
             WHERE r.created_at BETWEEN CAST(:start AS timestamptz) AND CAST(:end AS timestamptz)",
            $range
        );

        $credit = $this->row(
            "SELECT coalesce(sum(payout_amount), 0)::numeric(12,2) AS total
             FROM buy_ins
             WHERE payout_type = 'store_credit'
               AND created_at BETWEEN CAST(:start AS timestamptz) AND CAST(:end AS timestamptz)",
            $range
        );

        $inArchive = (int) $this->pdo->query("SELECT count(*) FROM items WHERE status = 'in_archive'")->fetchColumn();

        $revenue = (float) $sales['revenue'];
        $onWay = (int) $where['on_way'];
        $inRoom = (int) $where['in_room'];
        $bought = (int) $where['bought'];
        $returned = (int) $where['returned'];
        $decided = $bought + $returned;
        $tried = $inRoom + $decided;
        $requested = $onWay + $tried;

        return [
            'period'       => $period,
            'in_archive'   => $inArchive,
            'sold'         => (int) $sales['sold'],
            'revenue'      => (string) $sales['revenue'],
            // Of every euro taken in, how much is profit: (Σ sale − Σ purchase) / Σ sale.
            'gross_margin' => $revenue > 0 ? ($revenue - (float) $sales['cost']) / $revenue : null,
            // Buy rate: of the items requested in the period, bought / decided (bought or returned).
            'conversion'   => $decided > 0 ? (float) $bought / $decided : null,
            'tried_share'  => $requested > 0 ? (float) $tried / $requested : null,
            'funnel'       => [
                'requested' => $requested,
                'on_way'    => $onWay,
                'tried'     => $tried,
                'in_room'   => $inRoom,
                'decided'   => $decided,
                'bought'    => $bought,
                'returned'  => $returned,
            ],
            'store_credit' => (string) $credit['total'],
        ];
    }

    /**
     * Items in the archive for more than 60 days since their buy-in date, oldest first.
     * Days are calendar days in Porto.
     *
     * @return list<array{title: string, size: string, shelf_code: string, sale_price: string, days: int}>
     */
    public function slowMovers(DateTimeImmutable $now): array
    {
        $today = $now->setTimezone(new DateTimeZone(self::TIMEZONE))->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            "SELECT title, size, shelf_code, sale_price, days
             FROM (
                 SELECT i.id, i.title, i.size, i.shelf_code, i.sale_price, b.created_at,
                        CAST(:today AS date) - (b.created_at AT TIME ZONE :tz)::date AS days
                 FROM items i JOIN buy_ins b ON b.id = i.buy_in_id
                 WHERE i.status = 'in_archive'
             ) AS archive
             WHERE days > :limit
             ORDER BY days DESC, created_at, id"
        );
        $stmt->execute(['today' => $today, 'tz' => self::TIMEZONE, 'limit' => self::SLOW_MOVER_DAYS]);

        return array_map(static fn (array $r): array => [
            'title'      => $r['title'],
            'size'       => $r['size'],
            'shelf_code' => $r['shelf_code'],
            'sale_price' => $r['sale_price'],
            'days'       => (int) $r['days'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function row(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
