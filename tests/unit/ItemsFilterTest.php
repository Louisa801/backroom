<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Validation of filter input. No database needed. */
final class ItemsFilterTest extends TestCase
{
    public function testEmptyQueryMeansNoFilters(): void
    {
        self::assertSame([], Items::normaliseFilters([]));
        self::assertSame([], Items::normaliseFilters(['size' => '', 'category' => '', 'colour' => '', 'max_price' => '']));
    }

    public function testValidValuesAreKept(): void
    {
        self::assertSame(
            ['size' => 'M', 'category' => 3, 'colour' => 'navy', 'max_price' => '25.00'],
            Items::normaliseFilters(['size' => 'M', 'category' => '3', 'colour' => 'navy', 'max_price' => '25'])
        );
    }

    public function testTrimsWhitespaceAndKeepsCents(): void
    {
        self::assertSame(['size' => 'XL', 'max_price' => '19.50'], Items::normaliseFilters(['size' => ' XL ', 'max_price' => '19.5']));
    }

    public function testIgnoresUnknownKeys(): void
    {
        self::assertSame(['colour' => 'red'], Items::normaliseFilters(['colour' => 'red', 'status' => 'sold']));
    }

    #[DataProvider('invalidQueries')]
    public function testInvalidValuesAreRejected(array $query): void
    {
        $this->expectException(InvalidArgumentException::class);
        Items::normaliseFilters($query);
    }

    public static function invalidQueries(): array
    {
        return [
            'unknown size'        => [['size' => 'XXXL']],
            'lowercase size'      => [['size' => 'm']],
            'size as array'       => [['size' => ['M', 'L']]],
            'category not number' => [['category' => 'tops']],
            'category zero'       => [['category' => '0']],
            'category negative'   => [['category' => '-2']],
            'unknown colour'      => [['colour' => 'purple']],
            'price text'          => [['max_price' => 'abc']],
            'price negative'      => [['max_price' => '-5']],
            'price zero'          => [['max_price' => '0']],
            'price three decimals'=> [['max_price' => '10.555']],
            'sql in colour'       => [['colour' => "navy' OR '1'='1"]],
        ];
    }
}
