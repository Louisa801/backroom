<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Saving a buy-in against the real database. Everything is rolled back. */
final class IntakeSaveTest extends TestCase
{
    private PDO $pdo;
    private Intake $intake;
    private int $staffId;
    private int $categoryId;

    protected function setUp(): void
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $this->pdo = db();
        $this->pdo->beginTransaction();
        $this->intake = new Intake($this->pdo);
        $this->staffId = test_staff_id($this->pdo);
        $this->categoryId = (int) $this->pdo->query('SELECT min(id) FROM categories')->fetchColumn();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function photoId(): int
    {
        $img = imagecreatetruecolor(20, 25);
        ob_start();
        imagejpeg($img);
        return (new Photos($this->pdo))->store(['data' => (string) ob_get_clean(), 'width' => 20, 'height' => 25]);
    }

    private function item(string $shelf, int $purchaseCents, int $saleCents): array
    {
        return [
            'title' => "Save test $shelf", 'category_id' => $this->categoryId, 'size' => 'L', 'colour' => 'green',
            'condition' => 'like_new', 'purchase_cents' => $purchaseCents, 'sale_cents' => $saleCents,
            'shelf_code' => $shelf, 'photo_id' => $this->photoId(),
        ];
    }

    private function rowCount(string $table): int
    {
        return (int) $this->pdo->query("SELECT count(*) FROM $table")->fetchColumn();
    }

    public function testSavesBuyInAndItemsWithStoreCreditPayout(): void
    {
        $data = ['payout_type' => 'store_credit', 'items' => [
            $this->item('Y-01', 1250, 4000),
            $this->item('Y-02', 2150, 6000),
        ]];

        $buyInId = $this->intake->save($data, $this->staffId);

        $buyIn = $this->pdo->query("SELECT payout_type, cash_amount, payout_amount, staff_id FROM buy_ins WHERE id = $buyInId")->fetch();
        self::assertSame(['payout_type' => 'store_credit', 'cash_amount' => '34.00', 'payout_amount' => '40.80', 'staff_id' => $this->staffId], $buyIn);

        $items = $this->pdo->query("SELECT shelf_code, status, purchase_price, sale_price, photo_id IS NOT NULL AS has_photo, photo_path FROM items WHERE buy_in_id = $buyInId ORDER BY shelf_code")->fetchAll();
        self::assertSame([
            ['shelf_code' => 'Y-01', 'status' => 'in_archive', 'purchase_price' => '12.50', 'sale_price' => '40.00', 'has_photo' => true, 'photo_path' => null],
            ['shelf_code' => 'Y-02', 'status' => 'in_archive', 'purchase_price' => '21.50', 'sale_price' => '60.00', 'has_photo' => true, 'photo_path' => null],
        ], $items);
    }

    public function testSavedItemsAppearInTheArchive(): void
    {
        $buyInId = $this->intake->save(['payout_type' => 'cash', 'items' => [$this->item('Y-03', 500, 1500)]], $this->staffId);

        $titles = array_column((new Items($this->pdo))->filterArchive([]), 'title');
        self::assertContains('Save test Y-03', $titles);

        $summary = $this->intake->summary($buyInId);
        self::assertSame('5.00', $summary['payout_amount']);
        self::assertStringStartsWith('/photo.php?id=', $summary['items'][0]['photo_url']);
    }

    public function testShelfCodeTakenInTheMeantimeSavesNothing(): void
    {
        $taken = $this->pdo->query("SELECT shelf_code FROM items WHERE status <> 'sold' LIMIT 1")->fetchColumn();
        $buyIns = $this->rowCount('buy_ins');
        $items = $this->rowCount('items');

        try {
            $this->intake->save(['payout_type' => 'cash', 'items' => [
                $this->item('Y-04', 500, 1500),
                $this->item($taken, 500, 1500),
            ]], $this->staffId);
            self::fail('Expected ShelfCodeTakenException');
        } catch (ShelfCodeTakenException $e) {
            self::assertSame([$taken], $e->codes);
        }

        self::assertSame($buyIns, $this->rowCount('buy_ins'), 'No buy-in was saved.');
        self::assertSame($items, $this->rowCount('items'), 'No item was saved.');
    }

    public function testFailureOnALaterItemRollsBackTheWholeBuyIn(): void
    {
        $buyIns = $this->rowCount('buy_ins');
        $bad = $this->item('Y-06', 500, 1500);
        $bad['size'] = 'XXXL'; // refused by the database CHECK

        try {
            $this->intake->save(['payout_type' => 'cash', 'items' => [$this->item('Y-05', 500, 1500), $bad]], $this->staffId);
            self::fail('Expected a database error');
        } catch (PDOException) {
            // expected
        }

        self::assertSame($buyIns, $this->rowCount('buy_ins'));
        self::assertSame(0, (int) $this->pdo->query("SELECT count(*) FROM items WHERE shelf_code = 'Y-05'")->fetchColumn());
    }
}
