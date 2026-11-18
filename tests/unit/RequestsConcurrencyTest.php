<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Two tablets requesting the same item at the same moment.
 *
 * Tablet A (this process) has requested item X but not committed yet, so X is locked.
 * Tablet B (a child process with its own connection) requests X and Y at that moment
 * and has to wait for the lock. When A commits, B must see that X is gone and get only Y.
 *
 * The fixtures must be committed so the child process can see them; everything this
 * test creates is deleted again in tearDown.
 */
final class RequestsConcurrencyTest extends TestCase
{
    private const WAIT_FOR_LOCK_SECONDS = 10;

    private PDO $admin;      // autocommit connection for fixtures and clean-up
    private PDO $tabletA;
    private int $staffId = 0;
    private int $buyInId = 0;
    private int $categoryId = 0;
    private int $itemX = 0;
    private int $itemY = 0;

    protected function setUp(): void
    {
        $url = getenv('DATABASE_URL');
        if ($url === false || $url === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $this->admin = self::connect($url);
        $this->tabletA = self::connect($url);

        $this->staffId = (int) $this->admin->query("INSERT INTO staff_users (email, password_hash, name) VALUES ('concurrency-test@backroom.test', 'x', 'Concurrency test') RETURNING id")->fetchColumn();
        $this->buyInId = (int) $this->admin->query("INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount) VALUES ({$this->staffId}, 'cash', 20, 20) RETURNING id")->fetchColumn();
        $this->categoryId = (int) $this->admin->query("INSERT INTO categories (name) VALUES ('Concurrency test') RETURNING id")->fetchColumn();

        $codes = Intake::suggestShelfCodes((new Intake($this->admin))->takenShelfCodes(), 2);
        $stmt = $this->admin->prepare(
            "INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code)
             VALUES (?, ?, ?, 'M', 'black', 'good', 10, 20, 'assets/test.svg', ?) RETURNING id"
        );
        $stmt->execute([$this->buyInId, $this->categoryId, 'Concurrency test X', $codes[0]]);
        $this->itemX = (int) $stmt->fetchColumn();
        $stmt->execute([$this->buyInId, $this->categoryId, 'Concurrency test Y', $codes[1]]);
        $this->itemY = (int) $stmt->fetchColumn();
    }

    protected function tearDown(): void
    {
        if (!isset($this->admin)) {
            return;
        }
        if (isset($this->tabletA) && $this->tabletA->inTransaction()) {
            $this->tabletA->rollBack();
        }
        // Delete everything this test created, children first.
        $items = [$this->itemX, $this->itemY];
        $requestIds = $this->admin->query(
            'SELECT DISTINCT request_id FROM request_items WHERE item_id IN (' . implode(',', $items) . ')'
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($requestIds !== []) {
            $list = implode(',', array_map('intval', $requestIds));
            $this->admin->exec("DELETE FROM request_items WHERE request_id IN ($list)");
            $this->admin->exec("DELETE FROM tryon_requests WHERE id IN ($list)");
        }
        $this->admin->exec('DELETE FROM items WHERE id IN (' . implode(',', $items) . ')');
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

    public function testSecondTabletWaitsAndOnlyGetsWhatIsStillFree(): void
    {
        // Tablet A requests X inside an open transaction: X is now locked.
        $this->tabletA->beginTransaction();
        $a = (new Requests($this->tabletA))->create([$this->itemX], 1);
        self::assertSame([$this->itemX], array_column($a['requested'], 'id'));

        // Tablet B asks for X and Y at the same moment, in its own process.
        $worker = dirname(__DIR__) . '/support/request_worker.php';
        $process = proc_open(
            [PHP_BINARY, $worker, '2', (string) $this->itemX, (string) $this->itemY],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process, 'Could not start the worker process.');

        try {
            self::assertTrue($this->waitUntilSomeoneWaitsForALock(), 'Tablet B never had to wait for the lock on item X.');

            // Tablet A finishes its request.
            $this->tabletA->commit();

            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        }

        self::assertSame(0, $exitCode, "Worker failed: $output $errors");
        $b = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame([$this->itemY], array_column($b['requested'], 'id'), 'Tablet B gets only Y.');
        self::assertSame([$this->itemX], array_column($b['taken'], 'id'), 'Tablet B is told X was taken.');
        self::assertNotNull($b['request_id']);

        $count = $this->admin->query("SELECT count(*) FROM request_items WHERE item_id = {$this->itemX}")->fetchColumn();
        self::assertSame(1, (int) $count, 'Item X is in exactly one request.');
        $statuses = $this->admin->query("SELECT status FROM items WHERE id IN ({$this->itemX}, {$this->itemY}) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['requested', 'requested'], $statuses);
    }

    /** True once another session is waiting for a row lock held by tablet A. */
    private function waitUntilSomeoneWaitsForALock(): bool
    {
        $pidA = (int) $this->tabletA->query('SELECT pg_backend_pid()')->fetchColumn();
        $deadline = microtime(true) + self::WAIT_FOR_LOCK_SECONDS;
        while (microtime(true) < $deadline) {
            $waiting = $this->admin->query(
                "SELECT count(*) FROM pg_stat_activity WHERE $pidA = ANY(pg_blocking_pids(pid))"
            )->fetchColumn();
            if ((int) $waiting > 0) {
                return true;
            }
            usleep(50_000);
        }
        return false;
    }
}
