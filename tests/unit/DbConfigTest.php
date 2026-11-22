<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DbConfigTest extends TestCase
{
    public function testParsesPostgresqlUrl(): void
    {
        $config = database_config('postgresql://backroom:secret@localhost:5432/backroom');

        self::assertSame('pgsql:host=localhost;port=5432;dbname=backroom', $config['dsn']);
        self::assertSame('backroom', $config['user']);
        self::assertSame('secret', $config['password']);
    }

    public function testAcceptsPostgresSchemeAndDefaultsPort(): void
    {
        // Render gives URLs like this one, without a port.
        $config = database_config('postgres://app:pw@dpg-abc123-a/backroom_db');

        self::assertSame('pgsql:host=dpg-abc123-a;port=5432;dbname=backroom_db', $config['dsn']);
    }

    public function testDecodesEncodedPassword(): void
    {
        $config = database_config('postgresql://app:p%40ss%3Aword@db.example.com:6543/shop');

        self::assertSame('p@ss:word', $config['password']);
        self::assertSame('pgsql:host=db.example.com;port=6543;dbname=shop', $config['dsn']);
    }

    public function testPassesSslmodeFromTheUrl(): void
    {
        // Render's external URL, used with psql and the tests from a laptop.
        $config = database_config('postgresql://app:pw@dpg-abc123-a.frankfurt-postgres.render.com/backroom_db?sslmode=require');

        self::assertSame('pgsql:host=dpg-abc123-a.frankfurt-postgres.render.com;port=5432;dbname=backroom_db;sslmode=require', $config['dsn']);
        self::assertSame('pw', $config['password']);
    }

    public function testIgnoresOtherQueryParameters(): void
    {
        $config = database_config('postgresql://app:pw@localhost:5432/shop?application_name=x&sslmode=verify-full');
        self::assertSame('pgsql:host=localhost;port=5432;dbname=shop;sslmode=verify-full', $config['dsn']);

        $plain = database_config('postgresql://app:pw@localhost:5432/shop?application_name=x');
        self::assertSame('pgsql:host=localhost;port=5432;dbname=shop', $plain['dsn']);
    }

    public function testRefusesAnUnknownSslmode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown sslmode');
        database_config('postgresql://app:pw@localhost/shop?sslmode=always;host=evil');
    }

    #[DataProvider('badUrls')]
    public function testRejectsMalformedUrl(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        database_config($url);
    }

    public static function badUrls(): array
    {
        return [
            'empty'          => [''],
            'wrong scheme'   => ['mysql://u:p@localhost/db'],
            'no database'    => ['postgresql://u:p@localhost:5432/'],
            'no user'        => ['postgresql://localhost:5432/db'],
            'not a url'      => ['backroom'],
        ];
    }
}
