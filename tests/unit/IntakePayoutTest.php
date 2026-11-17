<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IntakePayoutTest extends TestCase
{
    public function testCashIsTheSumOfPurchasePrices(): void
    {
        self::assertSame(['cash' => 3400, 'payout' => 3400], Intake::payout([1250, 950, 1200], 'cash'));
    }

    public function testStoreCreditIsCashTimesOnePointTwo(): void
    {
        self::assertSame(['cash' => 3400, 'payout' => 4080], Intake::payout([1250, 950, 1200], 'store_credit'));
    }

    #[DataProvider('roundingCases')]
    public function testStoreCreditRoundsHalfUpToTheCent(int $cashCents, int $expectedCents): void
    {
        self::assertSame($expectedCents, Intake::payout([$cashCents], 'store_credit')['payout']);
    }

    public static function roundingCases(): array
    {
        return [
            '€10.04 -> 12.048 -> €12.05' => [1004, 1205],
            '€0.01 -> 0.012 -> €0.01'    => [1, 1],
            '€0.04 -> 0.048 -> €0.05'    => [4, 5],
            '€0.05 -> exactly €0.06'     => [5, 6],
            '€2.50 -> €3.00'             => [250, 300],
            '€58.50 -> €70.20'           => [5850, 7020],
            'zero'                       => [0, 0],
        ];
    }

    public function testRejectsUnknownPayoutType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Intake::payout([1000], 'voucher');
    }

    #[DataProvider('prices')]
    public function testParsesPricesWithDotOrComma(string $input, ?int $cents): void
    {
        self::assertSame($cents, Intake::parsePrice($input));
    }

    public static function prices(): array
    {
        return [
            ['12', 1200], ['12.5', 1250], ['12.50', 1250], ['12,50', 1250], [' 7,05 ', 705], ['0', 0],
            ['', null], ['abc', null], ['-5', null], ['12.555', null], ['1.234,50', null], ['€12', null], ['12.', null],
        ];
    }

    public function testFormatsCentsForTheDatabase(): void
    {
        self::assertSame('12.50', Intake::centsToDecimal(1250));
        self::assertSame('0.05', Intake::centsToDecimal(5));
        self::assertSame('999999.99', Intake::centsToDecimal(99999999));
    }

    /** The database CHECK on buy_ins must accept every payout this function produces. */
    public function testAgreesWithTheDatabaseCheck(): void
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $staffId = current_staff_id($pdo);
            $stmt = $pdo->prepare("INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount) VALUES (?, 'store_credit', ?, ?)");
            foreach ([1, 4, 5, 1004, 1234, 5850, 99999, 123457] as $cash) {
                $payout = Intake::payout([$cash], 'store_credit');
                $stmt->execute([$staffId, Intake::centsToDecimal($payout['cash']), Intake::centsToDecimal($payout['payout'])]);
            }
            $this->addToAssertionCount(1); // no constraint violation
        } finally {
            $pdo->rollBack();
        }
    }
}
