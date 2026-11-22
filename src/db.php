<?php
declare(strict_types=1);

/** sslmode values PostgreSQL understands; other values in the URL are refused. */
const DATABASE_SSLMODES = ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'];

/**
 * Turn a DATABASE_URL (postgres://user:pass@host:port/dbname[?sslmode=require]) into PDO settings.
 * Render's internal URL has no port and no sslmode; its external URL needs sslmode=require.
 * Other query parameters are ignored.
 *
 * @return array{dsn: string, user: string, password: string}
 */
function database_config(string $url): array
{
    $parts = parse_url($url);
    if ($parts === false
        || !in_array($parts['scheme'] ?? '', ['postgres', 'postgresql'], true)
        || empty($parts['host'])
        || empty($parts['user'])
        || trim($parts['path'] ?? '', '/') === ''
    ) {
        throw new InvalidArgumentException('DATABASE_URL must look like postgresql://user:password@host:port/database');
    }

    $port = $parts['port'] ?? 5432;
    $dbname = rawurldecode(trim($parts['path'], '/'));
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $parts['host'], $port, $dbname);

    parse_str($parts['query'] ?? '', $query);
    if (isset($query['sslmode'])) {
        if (!is_string($query['sslmode']) || !in_array($query['sslmode'], DATABASE_SSLMODES, true)) {
            throw new InvalidArgumentException('DATABASE_URL has an unknown sslmode. Use for example ?sslmode=require');
        }
        $dsn .= ';sslmode=' . $query['sslmode'];
    }

    return [
        'dsn'      => $dsn,
        'user'     => rawurldecode($parts['user']),
        'password' => rawurldecode($parts['pass'] ?? ''),
    ];
}

/**
 * Run $work inside a transaction: commit if it returns, roll back if it throws.
 * If a transaction is already open (e.g. in tests), a savepoint is used instead.
 */
function transaction(PDO $pdo, callable $work): mixed
{
    static $savepoint = 0;

    if ($pdo->inTransaction()) {
        $name = 'sp_' . ++$savepoint;
        $pdo->exec("SAVEPOINT $name");
        try {
            $result = $work();
            $pdo->exec("RELEASE SAVEPOINT $name");
            return $result;
        } catch (Throwable $e) {
            $pdo->exec("ROLLBACK TO SAVEPOINT $name");
            throw $e;
        }
    }

    $pdo->beginTransaction();
    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** One shared PDO connection per request. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $url = getenv('DATABASE_URL');
    if ($url === false || $url === '') {
        throw new RuntimeException('DATABASE_URL is not set.');
    }

    $config = database_config($url);
    $pdo = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
