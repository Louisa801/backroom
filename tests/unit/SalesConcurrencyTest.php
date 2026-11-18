<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Two staff members pressing "Sold" for the same item at the same moment.
 *
 * Staff member A (this process) has pressed "Sold" but not committed yet, so the item is
 * locked. Staff member B (a child process with its own connection) presses "Sold" too and
 * has to wait. When A commits, B must be refused and there must be exactly one sale.
 *
 * The fixtures must be committed so the child process can see them; everything this
 * test creates is deleted again in tearDown.
 */
final class SalesConcurrencyTest extends TestCase
{
    private const WAIT_FOR_LOCK_SECONDS = 10;

    private PDO $admin;
    private PDO $staffA;
    private int $staffId = 0;
    private int $buyInId = 0;
    private int $categoryId = 0;
    private int $itemId = 0;
    private int $otherItemId = 0;
    private int $requestId = 0;

    protected function setUp(): void
    {
        $url = getenv('DATABASE_URL');
        if ($url === false || $url === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $this->admin = self::connect($url);
        $this->staffA = self::connect($url);

        $this->staffId = (int) $this->admin->query("INSERT INTO staff_users (email, password_hash, name) VALUES ('sales-concurrency@backroom.test', 'x', 'Sales concurrency') RETURNING id")->fetchColumn();
        $this->buyInId = (int) $this->admin->query("INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount) VALUES ({$this->staffId}, 'cash', 20, 20) RETURNING id")->fetchColumn();
        $this->categoryId = (int) $this->admin->query("INSERT INTO categories (name) VALUES ('Sales concurrency') RETURNING id")->fetchColumn();

        $codes = Intake::suggestShelfCodes((new Intake($this->admin))->takenShelfCodes(), 2);
        $stmt = $this->admin->prepare(
            "INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code, status)
             VALUES (?, ?, ?, 'M', 'black', 'good', 10, 40, 'assets/test.svg', ?, 'in_fitting_room') RETURNING id"
        );
        $stmt->execute([$this->buyInId, $this->categoryId, 'Sales concurrency coat', $codes[0]]);
        $this->itemId = (int) $stmt->fetchColumn();
        $stmt->execute([$this->buyInId, $this->categoryId, 'Sales concurrency shirt', $codes[1]]);
        $this->otherItemId = (int) $stmt->fetchColumn();

        $this->requestId = (int) $this->admin->query(
            "INSERT INTO tryon_requests (fitting_room, status, created_at, ready_at) VALUES (1, 'ready', now() - interval '5 minutes', now()) RETURNING id"
        )->fetchColumn();
        $this->admin->exec("INSERT INTO request_items (request_id, item_id) VALUES ({$this->requestId}, {$this->itemId}), ({$this->requestId}, {$this->otherItemId})");
    }

    protected function tearDown(): void
    {
        if (!isset($this->admin)) {
            return;
        }
        if (isset($this->staffA) && $this->staffA->inTransaction()) {
            $this->staffA->rollBack();
        }
        // Delete everything this test created, children first.
        $items = "{$this->itemId}, {$this->otherItemId}";
        $this->admin->exec("DELETE FROM sales WHERE item_id IN ($items)");
        $this->admin->exec("DELETE FROM request_items WHERE request_id = {$this->requestId}");
        $this->admin->exec("DELETE FROM tryon_requests WHERE id = {$this->requestId}");
        $this->admin->exec("DELETE FROM items WHERE id IN ($items)");
        $this->admin->exec("DELETE FROM buy_ins WHERE id = {$this->buyInId}");
        $this->admin->exec("DELETE FROM categories WHERE id = {$this->categoryId}");
        $this->admin->exec("DELETE FROM staff_users WHERE id = {$this->staffId}");
    }

    private static function connect(string $url): PDO
    {
        $config = database_config($url);
        return new PDO($config['dsn'], $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public function testSecondSoldWaitsAndIsRefused(): void
    {
        // Staff member A presses "Sold" inside an open transaction: the item is locked.
        $this->staffA->beginTransaction();
        $a = (new Sales($this->staffA))->sell($this->requestId, $this->itemId);
        self::assertSame('Sales concurrency coat', $a['title']);

        // Staff member B presses "Sold" for the same item at the same moment.
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/support/sale_worker.php', (string) $this->requestId, (string) $this->itemId],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process, 'Could not start the worker process.');

        try {
            self::assertTrue($this->waitUntilSomeoneWaitsForALock(), 'Staff member B never had to wait for the lock.');
            $this->staffA->commit();
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        }

        self::assertSame(0, $exitCode, "Worker failed: $output $errors");
        $b = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($b['sold'], 'Staff member B must not sell the item again.');
        self::assertStringContainsString('already sold', $b['refused']);

        self::assertSame(1, (int) $this->admin->query("SELECT count(*) FROM sales WHERE item_id = {$this->itemId}")->fetchColumn());
        self::assertSame('sold', $this->admin->query("SELECT status FROM items WHERE id = {$this->itemId}")->fetchColumn());
    }

    private function waitUntilSomeoneWaitsForALock(): bool
    {
        $pidA = (int) $this->staffA->query('SELECT pg_backend_pid()')->fetchColumn();
        $deadline = microtime(true) + self::WAIT_FOR_LOCK_SECONDS;
        while (microtime(true) < $deadline) {
            $waiting = $this->admin->query("SELECT count(*) FROM pg_stat_activity WHERE $pidA = ANY(pg_blocking_pids(pid))")->fetchColumn();
            if ((int) $waiting > 0) {
                return true;
            }
            usleep(50_000);
        }
        return false;
    }
}
