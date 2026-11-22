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

    public function testFormatsWaitingTimes(): void
    {
        self::assertSame('just now', format_waiting(0));
        self::assertSame('waiting 1 min', format_waiting(1));
        self::assertSame('waiting 59 min', format_waiting(59));
        self::assertSame('waiting 1 h', format_waiting(60));
        self::assertSame('waiting 1 h 1 min', format_waiting(61));
        self::assertSame('waiting 2 h 23 min', format_waiting(143));
        self::assertSame('waiting 3 h', format_waiting(180));
    }

    public function testFormatsWholeEuros(): void
    {
        self::assertSame('€204', format_whole_euros('204.00'));
        self::assertSame('€205', format_whole_euros('204.50'));
        self::assertSame('€204', format_whole_euros('204.49'));
        self::assertSame('€0', format_whole_euros('0.00'));
        self::assertSame('€1,250', format_whole_euros('1250.00'));
    }

    public function testFormatsPricesInEuros(): void
    {
        self::assertSame('€28.00', format_price('28'));
        self::assertSame('€28.50', format_price('28.5'));
        self::assertSame('€8.00', format_price(8));
        self::assertSame('€1,250.00', format_price('1250.00'));
    }
}
