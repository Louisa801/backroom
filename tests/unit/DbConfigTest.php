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
