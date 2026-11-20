<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Login checks with a fixed "now". Everything is rolled back. */
final class AuthTest extends TestCase
{
    private const EMAIL = 'auth-test@backroom.test';
    private const PASSWORD = 'correct horse battery';

    private PDO $pdo;
    private int $staffId;

    protected function setUp(): void
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $this->pdo = db();
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare('INSERT INTO staff_users (email, password_hash, name) VALUES (?, ?, ?) RETURNING id');
        $stmt->execute([self::EMAIL, password_hash(self::PASSWORD, PASSWORD_DEFAULT), 'Ana Test']);
        $this->staffId = (int) $stmt->fetchColumn();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'));
    }

    private function login(string $email, string $password, string $time = '2031-03-12 15:00:00'): array
    {
        return attempt_login($this->pdo, $email, $password, self::at($time));
    }

    private function failures(string $email): int
    {
        $stmt = $this->pdo->prepare('SELECT count(*) FROM login_failures WHERE email = ?');
        $stmt->execute([$email]);
        return (int) $stmt->fetchColumn();
    }

    // ---------------- right and wrong ----------------

    public function testCorrectLoginReturnsTheStaffMember(): void
    {
        $result = $this->login(self::EMAIL, self::PASSWORD);

        self::assertTrue($result['ok']);
        self::assertSame(['id' => $this->staffId, 'name' => 'Ana Test', 'email' => self::EMAIL], $result['staff']);
        self::assertArrayNotHasKey('error', $result);
    }

    public function testCorrectLoginClearsEarlierFailures(): void
    {
        $this->login(self::EMAIL, 'wrong 1', '2031-03-12 14:00:00');
        $this->login(self::EMAIL, 'wrong 2', '2031-03-12 14:00:01');
        self::assertSame(2, $this->failures(self::EMAIL));

        self::assertTrue($this->login(self::EMAIL, self::PASSWORD)['ok']);
        self::assertSame(0, $this->failures(self::EMAIL));
    }

    public function testWrongPasswordAndUnknownEmailGiveTheSameMessage(): void
    {
        $wrongPassword = $this->login(self::EMAIL, 'not the password');
        $unknownEmail = $this->login('nobody@backroom.test', self::PASSWORD);

        self::assertSame(['ok' => false, 'error' => 'Email or password is incorrect.'], $wrongPassword);
        self::assertSame($wrongPassword, $unknownEmail, 'Nothing reveals whether the email exists.');
        self::assertSame(1, $this->failures(self::EMAIL));
        self::assertSame(1, $this->failures('nobody@backroom.test'));
    }

    public function testEmailIgnoresCaseAndSpaces(): void
    {
        self::assertTrue($this->login('  Auth-Test@Backroom.TEST ', self::PASSWORD)['ok']);
    }

    public function testEmptyPasswordIsWrong(): void
    {
        self::assertFalse($this->login(self::EMAIL, '')['ok']);
    }

    // ---------------- guessing limit ----------------

    private function fiveFailures(string $email = self::EMAIL): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login($email, "guess $i", sprintf('2031-03-12 15:00:%02d', $i));
        }
    }

    public function testFourFailuresDoNotLock(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->login(self::EMAIL, "guess $i", sprintf('2031-03-12 15:00:%02d', $i));
        }
        self::assertTrue($this->login(self::EMAIL, self::PASSWORD, '2031-03-12 15:00:05')['ok']);
    }

    public function testAfterFiveFailuresEvenTheRightPasswordWaitsAMinute(): void
    {
        $this->fiveFailures(); // the last one at 15:00:04

        $locked = $this->login(self::EMAIL, self::PASSWORD, '2031-03-12 15:00:05');
        self::assertSame(['ok' => false, 'error' => 'Too many attempts. Please wait a minute and try again.'], $locked);

        self::assertFalse($this->login(self::EMAIL, self::PASSWORD, '2031-03-12 15:01:03')['ok'], '59 seconds later: still locked.');
        self::assertTrue($this->login(self::EMAIL, self::PASSWORD, '2031-03-12 15:01:05')['ok'], '61 seconds later: open again.');
    }

    public function testAfterTheMinuteCountingStartsAgain(): void
    {
        $this->fiveFailures();
        // A minute later one more wrong guess doesn't lock straight away.
        self::assertSame('Email or password is incorrect.', $this->login(self::EMAIL, 'again', '2031-03-12 15:01:10')['error']);
        self::assertTrue($this->login(self::EMAIL, self::PASSWORD, '2031-03-12 15:01:11')['ok']);
    }

    public function testLockedAttemptsDoNotCheckThePassword(): void
    {
        $this->fiveFailures();
        $this->login(self::EMAIL, 'during the lock', '2031-03-12 15:00:30');
        self::assertSame(5, $this->failures(self::EMAIL), 'Attempts during the lock are refused before any check.');
    }

    public function testTheLimitIsPerEmail(): void
    {
        $this->fiveFailures('someone-else@backroom.test');
        self::assertTrue($this->login(self::EMAIL, self::PASSWORD, '2031-03-12 15:00:05')['ok']);
    }

    public function testUnknownEmailsAreLockedTheSameWay(): void
    {
        $this->fiveFailures('nobody@backroom.test');
        self::assertSame('Too many attempts. Please wait a minute and try again.', $this->login('nobody@backroom.test', 'x', '2031-03-12 15:00:05')['error']);
    }

    // ---------------- return path ----------------

    #[DataProvider('returnPaths')]
    public function testOnlyLocalStaffPathsAreUsedAfterLogin(mixed $given, string $expected): void
    {
        self::assertSame($expected, safe_return_path($given));
    }

    public static function returnPaths(): array
    {
        return [
            'staff board'          => ['/staff/', '/staff/'],
            'buy-in'               => ['/staff/intake.php', '/staff/intake.php'],
            'dashboard with query' => ['/manager/?period=today', '/manager/?period=today'],
            'manager without slash'=> ['/manager', '/manager'],
            'missing'              => [null, '/staff/'],
            'empty'                => ['', '/staff/'],
            'not text'             => [['/staff/'], '/staff/'],
            'other site'           => ['https://evil.example/', '/staff/'],
            'protocol-relative'    => ['//evil.example/staff/', '/staff/'],
            'backslash trick'      => ['/\\evil.example', '/staff/'],
            'javascript'           => ['javascript:alert(1)', '/staff/'],
            'customer page'        => ['/request.php?id=1', '/staff/'],
            'look-alike folder'    => ['/staffroom/', '/staff/'],
            'climbing out'         => ['/staff/../index.php', '/staff/'],
            'double slash inside'  => ['/staff//evil.example', '/staff/'],
            'newline (header)'     => ["/staff/\r\nSet-Cookie: x=1", '/staff/'],
            'relative'             => ['staff/', '/staff/'],
        ];
    }

    // ---------------- idle timeout ----------------

    public function testSessionEndsAfterEightHoursWithoutActivity(): void
    {
        $now = 1_900_000_000;
        self::assertFalse(session_expired($now - (7 * 3600 + 59 * 60), $now), '7 h 59 min: still logged in.');
        self::assertFalse(session_expired($now - 8 * 3600, $now), 'Exactly 8 h: still logged in.');
        self::assertTrue(session_expired($now - (8 * 3600 + 60), $now), '8 h 1 min: logged out.');
        self::assertTrue(session_expired(null, $now), 'Never seen: logged out.');
    }
}
