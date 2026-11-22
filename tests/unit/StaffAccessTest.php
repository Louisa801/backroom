<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Access control over real HTTP: this test starts its own `php -S` on a free port.
 *
 * - Every .php file under public/staff/ and public/manager/ is found automatically and
 *   requested without a login, so a page added later can't be forgotten.
 * - The customer pages and endpoints must stay public.
 * - The login flow, logout and the buy-in recording the logged-in staff member.
 *
 * The server can only see committed data, so the fixtures are committed and deleted
 * again in tearDownAfterClass.
 */
final class StaffAccessTest extends TestCase
{
    private const EMAIL = 'access-test@backroom.test';
    private const PASSWORD = 'access test password';
    private const NAME = 'Access Tester';

    /** @var resource|null */
    private static $server = null;
    private static string $base = '';
    private static string $log = '';
    private static ?PDO $pdo = null;
    private static int $staffId = 0;
    private static int $buyInId = 0;
    private static int $itemId = 0;
    private static int $requestId = 0;
    private static int $photoId = 0;
    private static int $archiveItemId = 0;
    private static string $archiveShelf = '';
    /** @var list<string> */
    private static array $tempFiles = [];

    public static function setUpBeforeClass(): void
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            return; // each test skips itself
        }
        self::$pdo = db();
        self::createFixtures();
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        if (self::$pdo !== null) {
            self::deleteFixtures();
        }
        foreach (self::$tempFiles as $file) {
            @unlink($file);
        }
    }

    protected function setUp(): void
    {
        if (self::$pdo === null) {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
    }

    // ---------------- fixtures and server ----------------

    private static function createFixtures(): void
    {
        $pdo = self::$pdo;
        $stmt = $pdo->prepare('INSERT INTO staff_users (email, password_hash, name) VALUES (?, ?, ?) RETURNING id');
        $stmt->execute([self::EMAIL, password_hash(self::PASSWORD, PASSWORD_DEFAULT), self::NAME]);
        self::$staffId = (int) $stmt->fetchColumn();

        // A request and an uploaded photo, so the public pages can be checked with real ids.
        self::$buyInId = (int) $pdo->query('INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount) VALUES (' . self::$staffId . ", 'cash', 10, 10) RETURNING id")->fetchColumn();
        self::$photoId = (new Photos($pdo))->store(Photos::process(self::pngFile(40, 50)));
        $shelf = Intake::suggestShelfCodes((new Intake($pdo))->takenShelfCodes())[0];
        $stmt = $pdo->prepare(
            "INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_id, shelf_code, status)
             VALUES (?, (SELECT min(id) FROM categories), 'Access test item', 'M', 'black', 'good', 10, 20, ?, ?, 'requested') RETURNING id"
        );
        $stmt->execute([self::$buyInId, self::$photoId, $shelf]);
        self::$itemId = (int) $stmt->fetchColumn();

        // An item still in the archive, for the public item page.
        $shelf2 = Intake::suggestShelfCodes((new Intake($pdo))->takenShelfCodes())[0];
        $stmt = $pdo->prepare(
            "INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_path, shelf_code, status)
             VALUES (?, (SELECT min(id) FROM categories), 'Access test archive item', 'M', 'navy', 'like_new', 10, 33, 'assets/test.svg', ?, 'in_archive') RETURNING id"
        );
        $stmt->execute([self::$buyInId, $shelf2]);
        self::$archiveItemId = (int) $stmt->fetchColumn();
        self::$archiveShelf = $shelf2;

        self::$requestId = (int) $pdo->query("INSERT INTO tryon_requests (fitting_room, status) VALUES (1, 'new') RETURNING id")->fetchColumn();
        $pdo->exec('INSERT INTO request_items (request_id, item_id) VALUES (' . self::$requestId . ', ' . self::$itemId . ')');
    }

    private static function deleteFixtures(): void
    {
        $pdo = self::$pdo;
        $staff = self::$staffId;
        if ($staff === 0) {
            return;
        }
        // Everything bought in by the test user (also the buy-in made through the page).
        $items = "SELECT id FROM items WHERE buy_in_id IN (SELECT id FROM buy_ins WHERE staff_id = $staff)";
        $photos = $pdo->query("SELECT photo_id FROM items WHERE photo_id IS NOT NULL AND id IN ($items)")->fetchAll(PDO::FETCH_COLUMN);
        $requests = $pdo->query("SELECT DISTINCT request_id FROM request_items WHERE item_id IN ($items)")->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec("DELETE FROM request_items WHERE item_id IN ($items)");
        if ($requests !== []) {
            $pdo->exec('DELETE FROM tryon_requests WHERE id IN (' . implode(',', array_map('intval', $requests)) . ')');
        }
        $pdo->exec("DELETE FROM items WHERE id IN ($items)");
        if ($photos !== []) {
            $pdo->exec('DELETE FROM photos WHERE id IN (' . implode(',', array_map('intval', $photos)) . ')');
        }
        $pdo->exec("DELETE FROM buy_ins WHERE staff_id = $staff");
        $pdo->exec("DELETE FROM staff_users WHERE id = $staff");
        $pdo->exec("DELETE FROM login_failures WHERE email IN ('" . self::EMAIL . "', 'nobody-access@backroom.test')");
    }

    private static function startServer(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        self::$log = self::tempFile();
        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', dirname(__DIR__, 2) . '/public'],
            [0 => ['pipe', 'r'], 1 => ['file', self::$log, 'a'], 2 => ['file', self::$log, 'a']],
            $pipes
        );
        self::$base = "http://127.0.0.1:$port";

        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(100_000);
        }
        throw new RuntimeException('The test server did not start: ' . @file_get_contents(self::$log));
    }

    private static function tempFile(string $contents = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bkaccess');
        file_put_contents($path, $contents);
        self::$tempFiles[] = $path;
        return $path;
    }

    private static function pngFile(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 169, 71, 42));
        $path = self::tempFile();
        imagepng($img, $path);
        return $path;
    }

    // ---------------- HTTP ----------------

    /**
     * One request without following redirects. $jar is a cookie file (a "browser"); null = no cookies.
     *
     * @return array{status: int, location: ?string, body: string}
     */
    private function http(string $method, string $path, array $fields = [], ?string $jar = null, array $headers = []): array
    {
        $ch = curl_init(self::$base . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($method === 'POST') {
            $multipart = array_filter($fields, static fn ($v) => $v instanceof CURLFile) !== [];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query($fields));
        }
        if ($jar !== null) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        }
        $raw = curl_exec($ch);
        self::assertIsString($raw, "Request failed: $method $path " . curl_error($ch));
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch); // writes the cookie jar

        $head = substr($raw, 0, $headerSize);
        preg_match('/^Location:\s*(.+)$/mi', $head, $m);
        return ['status' => $status, 'location' => isset($m[1]) ? trim($m[1]) : null, 'body' => substr($raw, $headerSize)];
    }

    private function browser(): string
    {
        return self::tempFile();
    }

    private static function sessionId(string $jar): ?string
    {
        foreach (file($jar) ?: [] as $line) {
            $parts = explode("\t", trim($line));
            if (count($parts) === 7 && $parts[5] === 'backroom_session') {
                return $parts[6];
            }
        }
        return null;
    }

    private static function csrf(string $html): string
    {
        self::assertMatchesRegularExpression('/name="csrf_token" value="([0-9a-f]{64})"/', $html, 'No CSRF token on the page.');
        preg_match('/name="csrf_token" value="([0-9a-f]{64})"/', $html, $m);
        return $m[1];
    }

    private function logIn(string $jar, string $return = '/staff/'): array
    {
        $page = $this->http('GET', '/staff/login.php?return=' . rawurlencode($return), jar: $jar);
        return $this->http('POST', '/staff/login.php', [
            'csrf_token' => self::csrf($page['body']), 'email' => self::EMAIL, 'password' => self::PASSWORD, 'return' => $return,
        ], $jar);
    }

    // ---------------- every protected URL ----------------

    /** @return list<string> URL paths of every PHP file in the protected folders */
    private static function protectedPaths(): array
    {
        $public = dirname(__DIR__, 2) . '/public';
        $paths = [];
        foreach (['staff', 'manager'] as $folder) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$public/$folder", FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($public)));
                }
            }
        }
        sort($paths);
        return $paths;
    }

    public function testEveryStaffAndManagerUrlIsRefusedWithoutALogin(): void
    {
        $paths = self::protectedPaths();
        foreach (['/staff/index.php', '/staff/intake.php', '/staff/action.php', '/staff/board.php', '/staff/logout.php', '/manager/index.php'] as $known) {
            self::assertContains($known, $paths, 'The file scan must find the known pages.');
        }

        $urls = array_merge($paths, ['/staff/', '/manager/', '/manager/?period=today']);
        foreach ($urls as $url) {
            if ($url === '/staff/login.php') {
                continue; // the one page that must be open
            }
            foreach (['GET', 'POST'] as $method) {
                $response = $this->http($method, $url, ['action' => 'sell', 'request_id' => '1', 'item_id' => '1']);
                $label = "$method $url";

                self::assertNotSame(200, $response['status'], "$label must not be open without a login.");
                self::assertStringNotContainsString('<main', $response['body'], "$label must not show a page.");

                if (str_ends_with($url, '/board.php')) {
                    self::assertSame(401, $response['status'], "$label is an endpoint: 401.");
                } elseif (str_ends_with($url, '/logout.php')) {
                    self::assertSame($method === 'GET' ? 405 : 403, $response['status'], "$label: GET not allowed, POST needs the CSRF token.");
                } else {
                    self::assertSame(303, $response['status'], "$label redirects to the login.");
                    self::assertStringStartsWith('/staff/login.php?return=', (string) $response['location'], $label);
                }
            }
        }
    }

    public function testCustomerPagesStayPublic(): void
    {
        $public = [
            '/',
            '/request.php?id=' . self::$requestId,
            '/api/items.php',
            '/api/requests.php?id=' . self::$requestId,
            '/photo.php?id=' . self::$photoId,
            '/item.php?id=' . self::$archiveItemId,
        ];

        foreach ($public as $url) {
            $response = $this->http('GET', $url);
            self::assertSame(200, $response['status'], "$url must be open to customers.");
            self::assertNull($response['location'], "$url must not redirect.");
        }
    }

    // ---------------- item page ----------------

    public function testItemPageShowsTheItemWithoutShelfCode(): void
    {
        $page = $this->http('GET', '/item.php?id=' . self::$archiveItemId);

        self::assertSame(200, $page['status']);
        self::assertStringContainsString('Access test archive item', $page['body']);
        self::assertStringContainsString('Size M · Navy · Condition: Like new', $page['body']);
        self::assertStringContainsString('€33.00', $page['body']);
        self::assertStringContainsString('data-tryon', $page['body'], 'The try-on toggle is on the page.');
        self::assertStringContainsString('id="tryon"', $page['body'], 'The try-on bar is on the page.');
        self::assertStringNotContainsString(self::$archiveShelf, $page['body'], 'Customers never see the shelf code.');
    }

    public function testTakenOrUnknownItemGivesAFriendlyPage(): void
    {
        foreach (['/item.php?id=' . self::$itemId, '/item.php?id=999999999', '/item.php?id=abc', '/item.php'] as $url) {
            $page = $this->http('GET', $url);
            self::assertSame(404, $page['status'], $url);
            self::assertNull($page['location'], "$url must not redirect.");
            self::assertStringContainsString('This item has just been taken', $page['body'], $url);
            self::assertStringContainsString('Back to the archive', $page['body'], $url);
        }
    }

    public function testCardsLinkToTheItemPageWithTheFilters(): void
    {
        $archive = $this->http('GET', '/?size=M&colour=navy')['body'];
        $link = 'href="/item.php?id=' . self::$archiveItemId . '&amp;size=M&amp;colour=navy"';

        self::assertSame(2, substr_count($archive, $link), 'Photo and title both link to the item page, with the filters.');
        self::assertStringContainsString('<template id="card-template">', $archive);
        self::assertStringContainsString('class="card__link" href=""', $archive, 'The JS card template has the title link.');
    }

    public function testBackLinkKeepsValidFiltersOnly(): void
    {
        $page = $this->http('GET', '/item.php?id=' . self::$archiveItemId . '&size=M&colour=purple&max_price=25')['body'];
        self::assertStringContainsString('href="/?size=M&amp;max_price=25.00">← Back to the archive', $page, 'The invalid colour is dropped.');

        $plain = $this->http('GET', '/item.php?id=' . self::$archiveItemId)['body'];
        self::assertStringContainsString('href="/">← Back to the archive', $plain);
    }

    public function testStaffScreenLinkOnlyForLoggedInStaff(): void
    {
        $customerPages = ['/', '/request.php?id=' . self::$requestId];

        // A customer (no login) never sees it.
        foreach ($customerPages as $url) {
            self::assertStringNotContainsString('Staff screen', $this->http('GET', $url, jar: $this->browser())['body'], "$url without a login");
        }

        // A logged-in staff member gets a clear way back.
        $jar = $this->browser();
        $this->logIn($jar);
        foreach ($customerPages as $url) {
            self::assertStringContainsString('<a class="site-header__back" href="/staff/">← Staff screen</a>', $this->http('GET', $url, jar: $jar)['body'], "$url while logged in");
        }

        // After logging out it's gone again.
        $board = $this->http('GET', '/staff/', jar: $jar)['body'];
        $this->http('POST', '/staff/logout.php', ['csrf_token' => self::csrf($board)], $jar);
        self::assertStringNotContainsString('Staff screen', $this->http('GET', '/', jar: $jar)['body'], 'after logging out');
    }

    public function testCustomerPagesHaveAQuietStaffLinkStaffPagesDont(): void
    {
        foreach (['/', '/request.php?id=' . self::$requestId] as $url) {
            self::assertStringContainsString('href="/staff/login.php">Staff</a>', $this->http('GET', $url)['body'], "$url links to the staff login.");
        }

        $jar = $this->browser();
        $this->logIn($jar);
        foreach (['/staff/', '/staff/intake.php', '/manager/', '/staff/login.php'] as $url) {
            $body = $this->http('GET', $url, jar: $jar)['body'];
            self::assertStringNotContainsString('class="site-footer"', $body, "$url is a staff page: no customer footer.");
        }
    }

    // ---------------- login ----------------

    public function testWrongPasswordAndUnknownEmailLookTheSame(): void
    {
        $jar = $this->browser();
        $token = self::csrf($this->http('GET', '/staff/login.php', jar: $jar)['body']);

        $wrong = $this->http('POST', '/staff/login.php', ['csrf_token' => $token, 'email' => self::EMAIL, 'password' => 'wrong'], $jar);
        $unknown = $this->http('POST', '/staff/login.php', ['csrf_token' => $token, 'email' => 'nobody-access@backroom.test', 'password' => 'wrong'], $jar);

        self::assertSame(401, $wrong['status']);
        self::assertSame($wrong['status'], $unknown['status']);
        self::assertStringContainsString('Email or password is incorrect.', $wrong['body']);
        self::assertStringContainsString('Email or password is incorrect.', $unknown['body']);
    }

    public function testLoginWithoutCsrfTokenIsRefused(): void
    {
        $response = $this->http('POST', '/staff/login.php', ['email' => self::EMAIL, 'password' => self::PASSWORD], $this->browser());
        self::assertSame(403, $response['status']);
    }

    public function testLoginGoesBackToThePageAndRenewsTheSessionId(): void
    {
        $jar = $this->browser();

        $refused = $this->http('GET', '/manager/?period=today', jar: $jar);
        self::assertSame('/staff/login.php?return=' . rawurlencode('/manager/?period=today'), $refused['location']);

        $page = $this->http('GET', $refused['location'], jar: $jar);
        $before = self::sessionId($jar);
        self::assertNotNull($before);

        $login = $this->http('POST', '/staff/login.php', [
            'csrf_token' => self::csrf($page['body']), 'email' => self::EMAIL, 'password' => self::PASSWORD, 'return' => '/manager/?period=today',
        ], $jar);

        self::assertSame(303, $login['status']);
        self::assertSame('/manager/?period=today', $login['location']);
        $after = self::sessionId($jar);
        self::assertNotNull($after);
        self::assertNotSame($before, $after, 'session_regenerate_id(true) gives a new session id.');

        foreach (['/staff/', '/staff/intake.php', '/staff/board.php', '/manager/'] as $url) {
            $response = $this->http('GET', $url, jar: $jar);
            self::assertSame(200, $response['status'], "$url after logging in");
        }
        $board = $this->http('GET', '/staff/', jar: $jar)['body'];
        self::assertMatchesRegularExpression(
            '#class="staff-user__name"><span class="visually-hidden">Logged in as </span>' . preg_quote(self::NAME, '#') . '</span>#',
            $board,
            'The header shows the name (with "Logged in as" for screen readers).'
        );
    }

    public function testStaffHeaderHasAnAccessibleMenuButton(): void
    {
        $jar = $this->browser();
        $this->logIn($jar);

        foreach (['/staff/' => '/staff/', '/staff/intake.php' => '/staff/intake.php', '/manager/' => '/manager/'] as $url => $current) {
            $body = $this->http('GET', $url, jar: $jar)['body'];

            self::assertMatchesRegularExpression('#<button type="button" class="staff-menu-button" aria-expanded="false" aria-controls="staff-menu">#', $body, "$url: menu button");
            self::assertStringContainsString('</span> Menu', $body, "$url: the button is labelled Menu");

            // The panel holds the three staff screens, the customer archive and the Log out form.
            preg_match('#<div class="staff-menu" id="staff-menu">(.*?)</div>\s*</header>#s', $body, $panel);
            self::assertNotEmpty($panel, "$url: menu panel");
            self::assertStringContainsString('<a href="/">Archive</a>', $panel[1], "$url: link to the customer archive");
            foreach (['/staff/', '/staff/intake.php', '/manager/'] as $link) {
                self::assertStringContainsString('href="' . $link . '"', $panel[1], "$url: link to $link");
            }
            self::assertStringContainsString('action="/staff/logout.php"', $panel[1]);
            self::assertStringContainsString('href="' . $current . '" aria-current="page"', $panel[1], "$url: current page marked");
            self::assertSame(1, substr_count($panel[1], 'aria-current="page"'), "$url: one current page");
        }
    }

    public function testLoginNeverRedirectsToAnotherSite(): void
    {
        $login = $this->logIn($this->browser(), 'https://evil.example/');
        self::assertSame(303, $login['status']);
        self::assertSame('/staff/', $login['location']);
    }

    // ---------------- logout ----------------

    public function testLogoutNeedsPostAndCsrfAndEndsTheSession(): void
    {
        $jar = $this->browser();
        $this->logIn($jar);
        $board = $this->http('GET', '/staff/', jar: $jar);
        self::assertSame(200, $board['status']);

        self::assertSame(405, $this->http('GET', '/staff/logout.php', jar: $jar)['status'], 'A link can not log out.');
        self::assertSame(403, $this->http('POST', '/staff/logout.php', [], $jar)['status'], 'Without the token: refused.');
        self::assertSame(200, $this->http('GET', '/staff/', jar: $jar)['status'], 'Still logged in.');

        $logout = $this->http('POST', '/staff/logout.php', ['csrf_token' => self::csrf($board['body'])], $jar);
        self::assertSame(303, $logout['status']);
        self::assertSame('/staff/login.php', $logout['location']);
        self::assertStringContainsString('You are logged out.', $this->http('GET', '/staff/login.php', jar: $jar)['body']);

        $after = $this->http('GET', '/staff/', jar: $jar);
        self::assertSame(303, $after['status'], 'After logging out the board needs a login again.');
        self::assertSame(401, $this->http('GET', '/staff/board.php', jar: $jar)['status']);
    }

    // ---------------- buy-in ----------------

    public function testBuyInRecordsTheLoggedInStaffMember(): void
    {
        $jar = $this->browser();
        $this->logIn($jar, '/staff/intake.php');
        $form = $this->http('GET', '/staff/intake.php', jar: $jar);
        self::assertSame(200, $form['status']);
        preg_match('/id="item-0-shelf_code"[^>]*value="([A-Z]-\d{2})"/', $form['body'], $shelf);
        self::assertNotEmpty($shelf, 'The form suggests a shelf code.');

        $save = $this->http('POST', '/staff/intake.php', [
            'csrf_token'                => self::csrf($form['body']),
            'payout_type'               => 'cash',
            'items[0][title]'           => 'Access test coat',
            'items[0][category]'        => (string) self::$pdo->query('SELECT min(id) FROM categories')->fetchColumn(),
            'items[0][size]'            => 'M',
            'items[0][colour]'          => 'green',
            'items[0][condition]'       => 'good',
            'items[0][purchase_price]'  => '12,50',
            'items[0][sale_price]'      => '40',
            'items[0][shelf_code]'      => $shelf[1],
            'photo[0]'                  => new CURLFile(self::pngFile(300, 400), 'image/png', 'coat.png'),
        ], $jar);

        self::assertSame(303, $save['status'], 'The buy-in is saved. ' . strip_tags(substr($save['body'], 0, 2000)));
        self::assertMatchesRegularExpression('#^/staff/intake\.php\?saved=\d+$#', (string) $save['location']);
        $buyInId = (int) substr((string) $save['location'], strlen('/staff/intake.php?saved='));

        $staffId = (int) self::$pdo->query("SELECT staff_id FROM buy_ins WHERE id = $buyInId")->fetchColumn();
        self::assertSame(self::$staffId, $staffId, 'The buy-in records who was logged in, not the first staff user.');
    }
}
