<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testEscapesHtmlSpecialCharacters(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &apos;quoted&apos;',
            e('<script>alert("x")</script> & \'quoted\'')
        );
    }

    public function testEscapesNullAndNumbers(): void
    {
        self::assertSame('', e(null));
        self::assertSame('42', e(42));
    }

    public function testFormatsSharesAsWholePercentages(): void
    {
        self::assertSame('–', format_percent(null));
        self::assertSame('50%', format_percent(0.5));
        self::assertSame('33%', format_percent(1 / 3));
        self::assertSame('44%', format_percent(7 / 16));
        self::assertSame('0%', format_percent(0.0));
        self::assertSame('100%', format_percent(1.0));
    }

    public function testFormatsPricesInEuros(): void
    {
        self::assertSame('€28.00', format_price('28'));
        self::assertSame('€28.50', format_price('28.5'));
        self::assertSame('€8.00', format_price(8));
        self::assertSame('€1,250.00', format_price('1250.00'));
    }
}
