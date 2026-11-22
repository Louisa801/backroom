<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Items::findInArchive() for the customer detail page. Rolled back, own fixtures. */
final class ItemsDetailTest extends TestCase
{
    use RequestFixtures;

    public function testAnItemInTheArchiveIsReturnedWithoutItsShelfCode(): void
    {
        $id = $this->makeItem('Detail coat', 'in_archive', '45.00');

        $item = (new Items($this->pdo))->findInArchive($id);

        self::assertSame([
            'id' => $id, 'title' => 'Detail coat', 'category' => 'Fixtures', 'size' => 'M', 'colour' => 'black',
            'condition' => 'good', 'sale_price' => '45.00', 'photo_url' => '/assets/test.svg',
        ], $item);
        self::assertArrayNotHasKey('shelf_code', $item, 'Customers never see the shelf code.');
    }

    #[DataProvider('notInTheArchive')]
    public function testItemsNotInTheArchiveAreNotReturned(string $status): void
    {
        $id = $this->makeItem("Detail $status", $status);
        self::assertNull((new Items($this->pdo))->findInArchive($id));
    }

    public static function notInTheArchive(): array
    {
        return [['requested'], ['in_fitting_room'], ['sold']];
    }

    public function testUnknownItemIsNotReturned(): void
    {
        self::assertNull((new Items($this->pdo))->findInArchive(999999999));
    }

    public function testFilterQueryKeepsOnlyFilters(): void
    {
        self::assertSame('size=M&colour=navy', Items::filterQuery(['size' => 'M', 'colour' => 'navy']));
        self::assertSame('category=3&max_price=25.00', Items::filterQuery(['category' => 3, 'max_price' => '25.00', 'status' => 'sold']));
        self::assertSame('', Items::filterQuery([]));
    }
}
