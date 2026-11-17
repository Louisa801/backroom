<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Archive filtering against the real database (DATABASE_URL).
 * Every test runs inside a transaction that is rolled back, so the data is unchanged.
 * Fixtures use their own staff user, categories and buy-in, so the tests do not
 * depend on the seed contents.
 */
final class ItemsArchiveTest extends TestCase
{
    private const PREFIX = 'PHPUnit fixture: ';

    private PDO $pdo;
    private Items $items;
    private int $catTops;
    private int $catCoats;

    protected function setUp(): void
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            self::markTestSkipped('DATABASE_URL is not set, so the database tests cannot run.');
        }

        $this->pdo = db();
        $this->pdo->beginTransaction();
        $this->items = new Items($this->pdo);

        $staffId = $this->insert("INSERT INTO staff_users (email, password_hash, name) VALUES ('phpunit@backroom.test', 'x', 'PHPUnit') RETURNING id");
        $this->catTops = $this->insert("INSERT INTO categories (name) VALUES ('PHPUnit tops') RETURNING id");
        $this->catCoats = $this->insert("INSERT INTO categories (name) VALUES ('PHPUnit coats') RETURNING id");
        $buyIn = $this->insert("INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount) VALUES ($staffId, 'cash', 60.00, 60.00) RETURNING id");

        // title, category, size, colour, sale price, status, shelf
        $fixtures = [
            ['archive M navy 20',     $this->catTops,  'M', 'navy',  '20.00', 'in_archive',      'Z-01'],
            ['archive S navy 25',     $this->catTops,  'S', 'navy',  '25.00', 'in_archive',      'Z-02'],
            ['archive L black 60',    $this->catCoats, 'L', 'black', '60.00', 'in_archive',      'Z-03'],
            ['requested M navy 20',   $this->catTops,  'M', 'navy',  '20.00', 'requested',       'Z-04'],
            ['fitting room M navy 20',$this->catTops,  'M', 'navy',  '20.00', 'in_fitting_room', 'Z-05'],
            ['sold M navy 20',        $this->catTops,  'M', 'navy',  '20.00', 'sold',            'Z-06'],
        ];
        $stmt = $this->pdo->prepare(
            'INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code, status)
             VALUES (:buy_in, :category, :title, :size, :colour, \'good\', 10.00, :price, \'assets/test.svg\', :shelf, :status)'
        );
        foreach ($fixtures as [$title, $category, $size, $colour, $price, $status, $shelf]) {
            $stmt->execute([
                'buy_in' => $buyIn, 'category' => $category, 'title' => self::PREFIX . $title,
                'size' => $size, 'colour' => $colour, 'price' => $price, 'shelf' => $shelf, 'status' => $status,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testOnlyInArchiveItemsAreReturned(): void
    {
        $rows = $this->items->filterArchive([]);

        self::assertSame(['archive L black 60', 'archive M navy 20', 'archive S navy 25'], $this->fixtureTitles($rows));

        // And no seed item with another status slips through either.
        $ids = array_column($rows, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT DISTINCT status FROM items WHERE id IN ($in)");
        $stmt->execute($ids);
        self::assertSame(['in_archive'], $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testFiltersBySize(): void
    {
        $rows = $this->items->filterArchive(['size' => 'M']);

        self::assertSame(['archive M navy 20'], $this->fixtureTitles($rows));
        self::assertSame(['M'], array_values(array_unique(array_column($rows, 'size'))));
    }

    public function testFiltersByCategory(): void
    {
        $rows = $this->items->filterArchive(['category' => $this->catCoats]);

        self::assertSame(['archive L black 60'], $this->fixtureTitles($rows));
        self::assertCount(1, $rows, 'Only our own category, so only our fixture.');
        self::assertSame('PHPUnit coats', $rows[0]['category']);
    }

    public function testFiltersByColour(): void
    {
        $rows = $this->items->filterArchive(['colour' => 'navy']);

        self::assertSame(['archive M navy 20', 'archive S navy 25'], $this->fixtureTitles($rows));
        self::assertSame(['navy'], array_values(array_unique(array_column($rows, 'colour'))));
    }

    public function testMaxPriceIsInclusive(): void
    {
        $rows = $this->items->filterArchive(['max_price' => '25.00']);

        self::assertSame(['archive M navy 20', 'archive S navy 25'], $this->fixtureTitles($rows));
        foreach ($rows as $row) {
            self::assertLessThanOrEqual(25.0, (float) $row['sale_price']);
        }
    }

    public function testCombinedFiltersReturnOnlyItemsMatchingAll(): void
    {
        $rows = $this->items->filterArchive(['size' => 'M', 'colour' => 'navy', 'category' => $this->catTops, 'max_price' => '20.00']);

        self::assertSame(['archive M navy 20'], $this->fixtureTitles($rows));
        self::assertCount(1, $rows);
    }

    public function testNoMatchesReturnsEmptyList(): void
    {
        self::assertSame([], $this->items->filterArchive(['category' => $this->catCoats, 'colour' => 'pink']));
    }

    public function testRowsHaveEverythingTheCardNeeds(): void
    {
        $rows = $this->items->filterArchive(['category' => $this->catCoats]);

        self::assertSame(['id', 'title', 'size', 'colour', 'category', 'sale_price', 'photo_url'], array_keys($rows[0]));
        self::assertIsInt($rows[0]['id']);
        self::assertSame('60.00', $rows[0]['sale_price']);
        self::assertSame('/assets/test.svg', $rows[0]['photo_url']);
    }

    public function testPhotoUrlPrefersUploadedPhoto(): void
    {
        self::assertSame('/photo.php?id=12', Items::photoUrl(12, null));
        self::assertSame('/photo.php?id=12', Items::photoUrl('12', null));
        self::assertSame('/assets/uploads/seed/item-01.svg', Items::photoUrl(null, 'assets/uploads/seed/item-01.svg'));
    }

    public function testCategoriesAreListed(): void
    {
        $names = array_column($this->items->categories(), 'name');

        self::assertContains('PHPUnit tops', $names);
        self::assertContains('PHPUnit coats', $names);
    }

    private function insert(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /** Titles of our fixtures in the result, sorted, without the prefix. */
    private function fixtureTitles(array $rows): array
    {
        $titles = [];
        foreach ($rows as $row) {
            if (str_starts_with($row['title'], self::PREFIX)) {
                $titles[] = substr($row['title'], strlen(self::PREFIX));
            }
        }
        sort($titles);
        return $titles;
    }
}
