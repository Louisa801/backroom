<?php
declare(strict_types=1);

/**
 * Turn a DATABASE_URL (postgres://user:pass@host:port/dbname) into PDO settings.
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

    return [
        'dsn'      => sprintf('pgsql:host=%s;port=%d;dbname=%s', $parts['host'], $port, $dbname),
        'user'     => rawurldecode($parts['user']),
        'password' => rawurldecode($parts['pass'] ?? ''),
    ];
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
