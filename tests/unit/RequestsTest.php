<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Creating try-on requests against the real database.
 * Every test runs in a transaction that is rolled back; fixtures are the test's own.
 */
final class RequestsTest extends TestCase
{
    private PDO $pdo;
    private Requests $requests;
    /** @var array<string, int> fixture name => item id */
    private array $item = [];

    protected function setUp(): void
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $this->pdo = db();
        $this->pdo->beginTransaction();
        $this->requests = new Requests($this->pdo);

        $staff = (int) $this->pdo->query("INSERT INTO staff_users (email, password_hash, name) VALUES ('requests-test@backroom.test', 'x', 'Requests test') RETURNING id")->fetchColumn();
        $buyIn = (int) $this->pdo->query("INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount) VALUES ($staff, 'cash', 70, 70) RETURNING id")->fetchColumn();
        $category = (int) $this->pdo->query("INSERT INTO categories (name) VALUES ('Requests test') RETURNING id")->fetchColumn();

        $stmt = $this->pdo->prepare(
            "INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code, status)
             VALUES (:buy_in, :category, :title, 'M', 'black', 'good', 10, 20, 'assets/test.svg', :shelf, :status) RETURNING id"
        );
        $fixtures = [
            'free1' => 'in_archive', 'free2' => 'in_archive', 'free3' => 'in_archive',
            'free4' => 'in_archive', 'free5' => 'in_archive', 'free6' => 'in_archive',
            'requested' => 'requested', 'fitting' => 'in_fitting_room', 'sold' => 'sold',
        ];
        $n = 1;
        foreach ($fixtures as $name => $status) {
            $stmt->execute(['buy_in' => $buyIn, 'category' => $category, 'title' => "Requests test $name", 'shelf' => sprintf('X-%02d', $n++), 'status' => $status]);
            $this->item[$name] = (int) $stmt->fetchColumn();
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function itemStatus(int $itemId): string
    {
        return (string) $this->pdo->query("SELECT status FROM items WHERE id = $itemId")->fetchColumn();
    }

    private function countRows(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    // ---------------- normalise ----------------

    public function testSixItemsAreRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 5 items');
        Requests::normalise([1, 2, 3, 4, 5, 6], 1);
    }

    public function testFiveItemsAreAccepted(): void
    {
        self::assertSame(['ids' => [1, 2, 3, 4, 5], 'room' => 2], Requests::normalise([1, '2', 3, 4, 5], '2'));
    }

    public function testDuplicatesAreRemovedBeforeCounting(): void
    {
        self::assertSame(['ids' => [4, 7], 'room' => 3], Requests::normalise([4, 7, 4, '7', 4, 4], 3));
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInputIsRefused(mixed $ids, mixed $room, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        Requests::normalise($ids, $room);
    }

    public static function invalidInput(): array
    {
        return [
            'empty list'      => [[], 1, 'Add at least one item'],
            'not a list'      => ['5', 1, 'Add at least one item'],
            'bad item id'     => [[1, 'abc'], 1, 'not a valid item'],
            'zero item id'    => [[0], 1, 'not a valid item'],
            'negative id'     => [[-3], 1, 'not a valid item'],
            'room 0'          => [[1], 0, 'fitting room 1, 2 or 3'],
            'room 4'          => [[1], 4, 'fitting room 1, 2 or 3'],
            'room text'       => [[1], 'x', 'fitting room 1, 2 or 3'],
            'room missing'    => [[1], null, 'fitting room 1, 2 or 3'],
        ];
    }

    // ---------------- create ----------------

    public function testCreatesRequestAndSetsItemsToRequested(): void
    {
        $result = $this->requests->create([$this->item['free1'], $this->item['free2']], 2);

        self::assertIsInt($result['request_id']);
        self::assertSame([], $result['taken']);
        self::assertSame(['Requests test free1', 'Requests test free2'], array_column($result['requested'], 'title'));

        $request = $this->pdo->query("SELECT status, fitting_room FROM tryon_requests WHERE id = {$result['request_id']}")->fetch();
        self::assertSame(['status' => 'new', 'fitting_room' => 2], $request);
        self::assertSame(2, $this->countRows("SELECT count(*) FROM request_items WHERE request_id = {$result['request_id']} AND outcome IS NULL"));
        self::assertSame('requested', $this->itemStatus($this->item['free1']));
        self::assertSame('requested', $this->itemStatus($this->item['free2']));
    }

    public function testRequestedItemsDisappearFromTheArchive(): void
    {
        $this->requests->create([$this->item['free1']], 1);

        $titles = array_column((new Items($this->pdo))->filterArchive([]), 'title');
        self::assertNotContains('Requests test free1', $titles);
        self::assertContains('Requests test free2', $titles);
    }

    public function testOnlyItemsStillInTheArchiveAreTakenTheRestIsReported(): void
    {
        $missing = $this->item['sold'] + 100000;
        $result = $this->requests->create(
            [$this->item['free1'], $this->item['requested'], $this->item['fitting'], $this->item['sold'], $missing],
            3
        );

        self::assertIsInt($result['request_id']);
        self::assertSame([$this->item['free1']], array_column($result['requested'], 'id'));
        self::assertSame([$this->item['requested'], $this->item['fitting'], $this->item['sold'], $missing], array_column($result['taken'], 'id'));
        self::assertSame(['Requests test requested', 'Requests test fitting', 'Requests test sold', null], array_column($result['taken'], 'title'));

        // The unavailable items are untouched.
        self::assertSame('in_fitting_room', $this->itemStatus($this->item['fitting']));
        self::assertSame('sold', $this->itemStatus($this->item['sold']));
        self::assertSame(1, $this->countRows("SELECT count(*) FROM request_items WHERE request_id = {$result['request_id']}"));
    }

    public function testWhenEverythingIsTakenNoRequestIsCreated(): void
    {
        $requestsBefore = $this->countRows('SELECT count(*) FROM tryon_requests');

        $result = $this->requests->create([$this->item['requested'], $this->item['sold']], 1);

        self::assertNull($result['request_id']);
        self::assertSame([], $result['requested']);
        self::assertCount(2, $result['taken']);
        self::assertSame($requestsBefore, $this->countRows('SELECT count(*) FROM tryon_requests'));
        self::assertSame('requested', $this->itemStatus($this->item['requested']));
    }

    public function testTheSameItemCannotBeRequestedTwice(): void
    {
        $first = $this->requests->create([$this->item['free1'], $this->item['free2']], 1);
        $second = $this->requests->create([$this->item['free2'], $this->item['free3']], 2);

        self::assertSame([$this->item['free3']], array_column($second['requested'], 'id'));
        self::assertSame([$this->item['free2']], array_column($second['taken'], 'id'));
        self::assertSame(1, $this->countRows("SELECT count(*) FROM request_items WHERE item_id = {$this->item['free2']}"));
        self::assertNotSame($first['request_id'], $second['request_id']);
    }

    public function testCreateRefusesSixItemsAndChangesNothing(): void
    {
        $ids = array_map(fn (string $n) => $this->item["free$n"], ['1', '2', '3', '4', '5', '6']);
        try {
            $this->requests->create($ids, 1);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            // expected
        }
        self::assertSame('in_archive', $this->itemStatus($this->item['free1']));
    }

    public function testFiveItemsCanBeRequested(): void
    {
        $ids = array_map(fn (string $n) => $this->item["free$n"], ['1', '2', '3', '4', '5']);
        self::assertCount(5, $this->requests->create($ids, 1)['requested']);
    }

    // ---------------- status ----------------

    public function testStatusShowsRoomStatusAndItems(): void
    {
        $id = $this->requests->create([$this->item['free1']], 2)['request_id'];

        $status = $this->requests->status($id);

        self::assertSame('new', $status['status']);
        self::assertSame(2, $status['fitting_room']);
        self::assertSame([['title' => 'Requests test free1', 'size' => 'M', 'photo_url' => '/assets/test.svg']], $status['items']);
        self::assertStringContainsString('Request sent', $status['label']);

        $this->pdo->exec("UPDATE tryon_requests SET status = 'ready', ready_at = now() WHERE id = $id");
        self::assertSame('Ready in fitting room 2. You can go there now.', $this->requests->status($id)['label']);
    }

    public function testUnknownRequestHasNoStatus(): void
    {
        self::assertNull($this->requests->status(999999999));
    }

    public function testTakenItemsAreDescribedInPlainWords(): void
    {
        self::assertSame('Wool coat', Requests::describeTaken([['id' => 1, 'title' => 'Wool coat']]));
        self::assertSame('Wool coat and Silk blouse', Requests::describeTaken([['id' => 1, 'title' => 'Wool coat'], ['id' => 2, 'title' => 'Silk blouse']]));
        self::assertSame('A, B and an item that is no longer in the archive', Requests::describeTaken([['id' => 1, 'title' => 'A'], ['id' => 2, 'title' => 'B'], ['id' => 3, 'title' => null]]));
    }
}
