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

    public function testHttpsIsDetectedDirectlyAndBehindRendersProxy(): void
    {
        self::assertTrue(request_is_https(['HTTPS' => 'on']));
        self::assertTrue(request_is_https(['HTTP_X_FORWARDED_PROTO' => 'https']), 'Render forwards HTTPS as a header.');
        self::assertTrue(request_is_https(['HTTP_X_FORWARDED_PROTO' => 'HTTPS']));
        self::assertTrue(request_is_https(['HTTP_X_FORWARDED_PROTO' => 'https, http']), 'The first value is the browser\'s.');
        self::assertFalse(request_is_https([]), 'Plain http, e.g. php -S on a laptop.');
        self::assertFalse(request_is_https(['HTTPS' => 'off']));
        self::assertFalse(request_is_https(['HTTP_X_FORWARDED_PROTO' => 'http']));
        self::assertFalse(request_is_https(['HTTP_X_FORWARDED_PROTO' => 'http, https']));
    }

    public function testNoTokenInSessionIsRefused(): void
    {
        self::assertFalse(csrf_matches(null, 'abc123'));
        self::assertFalse(csrf_matches('', ''));
    }
}
