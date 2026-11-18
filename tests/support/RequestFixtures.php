<?php
declare(strict_types=1);

/**
 * Shared fixtures for the request/sale tests. Everything is created inside a
 * transaction that is rolled back in tearDown, so the database is unchanged.
 */
trait RequestFixtures
{
    private PDO $pdo;
    private int $buyInId;
    private int $categoryId;
    /** @var list<string> */
    private array $freeShelfCodes = [];

    protected function setUp(): void
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $this->pdo = db();
        $this->pdo->beginTransaction();

        $staff = (int) $this->pdo->query("INSERT INTO staff_users (email, password_hash, name) VALUES ('fixtures@backroom.test', 'x', 'Fixtures') RETURNING id")->fetchColumn();
        $this->buyInId = (int) $this->pdo->query("INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount) VALUES ($staff, 'cash', 0, 0) RETURNING id")->fetchColumn();
        $this->categoryId = (int) $this->pdo->query("INSERT INTO categories (name) VALUES ('Fixtures') RETURNING id")->fetchColumn();
        $this->freeShelfCodes = Intake::suggestShelfCodes((new Intake($this->pdo))->takenShelfCodes(), 20);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function makeItem(string $title, string $status = 'in_archive', string $price = '40.00'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code, status)
             VALUES (:buy_in, :category, :title, 'M', 'black', 'good', 10, :price, 'assets/test.svg', :shelf, :status) RETURNING id"
        );
        $stmt->execute([
            'buy_in' => $this->buyInId, 'category' => $this->categoryId, 'title' => $title,
            'price' => $price, 'shelf' => array_shift($this->freeShelfCodes), 'status' => $status,
        ]);
        return (int) $stmt->fetchColumn();
    }

    /** A request in the given status, with item statuses that match it. */
    private function makeRequest(array $itemIds, string $status = 'new', int $room = 1, string $age = '10 minutes'): int
    {
        $ready = in_array($status, ['ready', 'closed'], true) ? "now() - interval '1 minute'" : 'NULL';
        $closed = $status === 'closed' ? 'now()' : 'NULL';
        $stmt = $this->pdo->prepare(
            "INSERT INTO tryon_requests (fitting_room, status, created_at, ready_at, closed_at)
             VALUES (:room, :status, now() - CAST(:age AS interval), $ready, $closed) RETURNING id"
        );
        $stmt->execute(['room' => $room, 'status' => $status, 'age' => $age]);
        $requestId = (int) $stmt->fetchColumn();

        $itemStatus = match ($status) {
            'new', 'fetching' => 'requested',
            'ready'           => 'in_fitting_room',
            'closed'          => 'in_archive',
        };
        $outcome = $status === 'closed' ? 'returned' : null;
        foreach ($itemIds as $itemId) {
            $this->pdo->prepare('INSERT INTO request_items (request_id, item_id, outcome, outcome_at) VALUES (?, ?, ?, ' . ($outcome === null ? 'NULL' : 'now()') . ')')
                ->execute([$requestId, $itemId, $outcome]);
            $this->pdo->prepare('UPDATE items SET status = ? WHERE id = ?')->execute([$itemStatus, $itemId]);
        }
        return $requestId;
    }

    private function value(string $sql): mixed
    {
        return $this->pdo->query($sql)->fetchColumn();
    }

    private function itemStatus(int $itemId): string
    {
        return (string) $this->value("SELECT status FROM items WHERE id = $itemId");
    }

    private function requestStatus(int $requestId): string
    {
        return (string) $this->value("SELECT status FROM tryon_requests WHERE id = $requestId");
    }

    private function outcome(int $requestId, int $itemId): ?string
    {
        $v = $this->value("SELECT outcome FROM request_items WHERE request_id = $requestId AND item_id = $itemId");
        return $v === false ? null : $v;
    }

    /** Run a transition and assert it is refused with a message containing $text. */
    private function assertRefused(callable $transition, string $text): void
    {
        try {
            $transition();
            self::fail('Expected the transition to be refused.');
        } catch (TransitionRefusedException $e) {
            self::assertStringContainsString($text, $e->getMessage());
            self::assertStringStartsWith('Nothing changed', $e->getMessage());
        }
    }
}
