<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShelfCodeTest extends TestCase
{
    public function testEmptyShopStartsAtA01(): void
    {
        self::assertSame(['A-01'], Intake::suggestShelfCodes([]));
    }

    public function testSkipsTakenCodes(): void
    {
        self::assertSame(['A-03'], Intake::suggestShelfCodes(['A-01', 'A-02']));
    }

    public function testFillsGapsFirst(): void
    {
        // A-02 was freed when its item was sold.
        self::assertSame(['A-02'], Intake::suggestShelfCodes(['A-01', 'A-03', 'A-04']));
    }

    public function testSuggestsSeveralDifferentCodes(): void
    {
        self::assertSame(['A-02', 'A-05', 'A-06'], Intake::suggestShelfCodes(['A-01', 'A-03', 'A-04'], 3));
    }

    public function testMovesToTheNextRackAfter99(): void
    {
        $rackA = array_map(static fn (int $n): string => sprintf('A-%02d', $n), range(1, 99));
        self::assertSame(['B-01'], Intake::suggestShelfCodes($rackA));
    }

    public function testIgnoresLetterCaseOfTakenCodes(): void
    {
        self::assertSame(['A-02'], Intake::suggestShelfCodes(['a-01']));
    }

    public function testFullShelvesGiveAClearError(): void
    {
        $all = [];
        foreach (range('A', 'Z') as $rack) {
            foreach (range(1, 99) as $n) {
                $all[] = sprintf('%s-%02d', $rack, $n);
            }
        }
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not enough free shelf spots');
        Intake::suggestShelfCodes($all);
    }

    public function testSoldItemsFreeTheirSpotInTheDatabase(): void
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $staffId = test_staff_id($pdo);
            $buyIn = (int) $pdo->query("INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount) VALUES ($staffId, 'cash', 20, 20) RETURNING id")->fetchColumn();
            $category = (int) $pdo->query('SELECT min(id) FROM categories')->fetchColumn();
            $stmt = $pdo->prepare(
                "INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code, status)
                 VALUES (?, ?, 'Shelf test', 'M', 'black', 'good', 10, 20, 'x.svg', ?, ?)"
            );
            $stmt->execute([$buyIn, $category, 'Y-01', 'sold']);
            $stmt->execute([$buyIn, $category, 'Y-02', 'in_archive']);
            $stmt->execute([$buyIn, $category, 'Y-03', 'requested']);

            $taken = (new Intake($pdo))->takenShelfCodes();

            self::assertNotContains('Y-01', $taken, 'A sold item frees its spot.');
            self::assertContains('Y-02', $taken);
            self::assertContains('Y-03', $taken, 'A requested item still has its spot.');
        } finally {
            $pdo->rollBack();
        }
    }
}
