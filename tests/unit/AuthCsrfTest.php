<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthCsrfTest extends TestCase
{
    public function testMatchingTokenIsAccepted(): void
    {
        self::assertTrue(csrf_matches('abc123', 'abc123'));
    }

    public function testWrongTokenIsRefused(): void
    {
        self::assertFalse(csrf_matches('abc123', 'abc124'));
    }

    public function testMissingTokenIsRefused(): void
    {
        self::assertFalse(csrf_matches('abc123', null));
        self::assertFalse(csrf_matches('abc123', ''));
        self::assertFalse(csrf_matches('abc123', ['abc123']));
    }

    public function testNoTokenInSessionIsRefused(): void
    {
        self::assertFalse(csrf_matches(null, 'abc123'));
        self::assertFalse(csrf_matches('', ''));
    }
}
