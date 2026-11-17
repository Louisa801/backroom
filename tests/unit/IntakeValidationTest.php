<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Validation of a buy-in. No database needed. */
final class IntakeValidationTest extends TestCase
{
    private const CATEGORIES = [1, 2, 3];

    private static function row(array $overrides = []): array
    {
        return array_merge([
            'title'          => 'Wool coat',
            'category'       => '1',
            'size'           => 'M',
            'colour'         => 'black',
            'condition'      => 'good',
            'purchase_price' => '12,50',
            'sale_price'     => '40',
            'shelf_code'     => 'e-01',
            'photo_id'       => 7,
            'photo_error'    => null,
        ], $overrides);
    }

    private static function validate(array $rows, string $payout = 'cash', array $taken = []): array
    {
        return Intake::validate($rows, $payout, self::CATEGORIES, $taken);
    }

    public function testValidBuyInPassesAndIsCleanedUp(): void
    {
        $result = self::validate([self::row(), self::row(['shelf_code' => 'E-02', 'photo_id' => 8])], 'store_credit');

        self::assertSame([], $result['errors']);
        self::assertSame('store_credit', $result['data']['payout_type']);
        self::assertSame([
            'title' => 'Wool coat', 'category_id' => 1, 'size' => 'M', 'colour' => 'black', 'condition' => 'good',
            'purchase_cents' => 1250, 'sale_cents' => 4000, 'shelf_code' => 'E-01', 'photo_id' => 7,
        ], $result['data']['items'][0]);
        self::assertSame('E-02', $result['data']['items'][1]['shelf_code']);
    }

    public function testSalePriceEqualToPurchasePriceIsAllowed(): void
    {
        self::assertSame([], self::validate([self::row(['purchase_price' => '10', 'sale_price' => '10.00'])])['errors']);
    }

    public function testPurchasePriceOfZeroIsAllowed(): void
    {
        self::assertSame([], self::validate([self::row(['purchase_price' => '0'])])['errors']);
    }

    #[DataProvider('missingFields')]
    public function testEveryMissingFieldGetsItsOwnMessage(string $field, string $message): void
    {
        $result = self::validate([self::row([$field => ''])]);

        self::assertSame($message, $result['errors']['rows'][0][$field]);
        self::assertCount(1, $result['errors']['rows'][0], 'Only the empty field is reported.');
    }

    public static function missingFields(): array
    {
        return [
            ['title', 'Please enter a title.'],
            ['category', 'Please choose a category.'],
            ['size', 'Please choose a size.'],
            ['colour', 'Please choose a colour.'],
            ['condition', 'Please choose the condition.'],
            ['purchase_price', 'Please enter the purchase price.'],
            ['sale_price', 'Please enter the sale price.'],
            ['shelf_code', 'Please enter a shelf code.'],
        ];
    }

    public function testMissingPhoto(): void
    {
        $errors = self::validate([self::row(['photo_id' => null])])['errors'];
        self::assertSame('Please add a photo.', $errors['rows'][0]['photo']);
    }

    public function testPhotoErrorFromTheUploadIsShown(): void
    {
        $errors = self::validate([self::row(['photo_id' => null, 'photo_error' => 'This file is not a photo.'])])['errors'];
        self::assertSame('This file is not a photo.', $errors['rows'][0]['photo']);
    }

    public function testNegativePrices(): void
    {
        $errors = self::validate([self::row(['purchase_price' => '-5', 'sale_price' => '-1'])])['errors']['rows'][0];
        self::assertSame("The purchase price can't be negative.", $errors['purchase_price']);
        self::assertSame("The sale price can't be negative.", $errors['sale_price']);
    }

    public function testSalePriceLowerThanPurchasePrice(): void
    {
        $errors = self::validate([self::row(['purchase_price' => '20', 'sale_price' => '15'])])['errors']['rows'][0];
        self::assertSame("The sale price can't be lower than the purchase price (€20.00).", $errors['sale_price']);
    }

    public function testSalePriceOfZero(): void
    {
        $errors = self::validate([self::row(['purchase_price' => '0', 'sale_price' => '0'])])['errors']['rows'][0];
        self::assertSame('The sale price must be more than €0.00.', $errors['sale_price']);
    }

    public function testPriceThatIsNotAnAmount(): void
    {
        $errors = self::validate([self::row(['purchase_price' => 'ten'])])['errors']['rows'][0];
        self::assertSame('Enter the purchase price as an amount in euros, like 12.50.', $errors['purchase_price']);
    }

    public function testTitleTooLong(): void
    {
        $errors = self::validate([self::row(['title' => str_repeat('é', 101)])])['errors']['rows'][0];
        self::assertSame('The title can be at most 100 characters.', $errors['title']);
        self::assertSame([], self::validate([self::row(['title' => str_repeat('é', 100)])])['errors']);
    }

    #[DataProvider('valuesNotOnTheList')]
    public function testValuesNotOnTheListAreRefused(string $field, string $value): void
    {
        self::assertArrayHasKey($field, self::validate([self::row([$field => $value])])['errors']['rows'][0]);
    }

    public static function valuesNotOnTheList(): array
    {
        return [
            'unknown category'  => ['category', '99'],
            'category text'     => ['category', 'Tops'],
            'unknown size'      => ['size', 'XXXL'],
            'unknown colour'    => ['colour', 'purple'],
            'unknown condition' => ['condition', 'mint'],
        ];
    }

    #[DataProvider('badShelfCodes')]
    public function testBadShelfCodeFormat(string $code): void
    {
        $errors = self::validate([self::row(['shelf_code' => $code])])['errors']['rows'][0];
        self::assertSame('Shelf codes look like A-01: a letter, a dash and two digits.', $errors['shelf_code']);
    }

    public static function badShelfCodes(): array
    {
        return [['A1'], ['A-1'], ['AA-01'], ['A-001'], ['1-01'], ['A-00'], ['A 01']];
    }

    public function testShelfCodeTakenInTheShop(): void
    {
        $errors = self::validate([self::row(['shelf_code' => 'a-08'])], 'cash', ['A-08'])['errors']['rows'][0];
        self::assertSame('Shelf A-08 is already taken by another item. Please choose another spot.', $errors['shelf_code']);
    }

    public function testSameShelfCodeTwiceInOneBuyIn(): void
    {
        $errors = self::validate([self::row(), self::row(['shelf_code' => 'E-01'])])['errors'];
        self::assertArrayNotHasKey(0, $errors['rows']);
        self::assertSame('Shelf E-01 is already used for item 1. Each item needs its own spot.', $errors['rows'][1]['shelf_code']);
    }

    public function testPayoutTypeMustBeChosen(): void
    {
        self::assertSame('Please choose cash or store credit.', self::validate([self::row()], '')['errors']['payout_type']);
        self::assertArrayHasKey('payout_type', self::validate([self::row()], 'voucher')['errors']);
    }

    public function testAtLeastOneItem(): void
    {
        self::assertSame('Add at least one item.', self::validate([])['errors']['items']);
    }

    public function testAtMostTenItems(): void
    {
        $rows = [];
        for ($n = 1; $n <= 11; $n++) {
            $rows[] = self::row(['shelf_code' => sprintf('E-%02d', $n), 'photo_id' => $n]);
        }
        self::assertStringContainsString('at most 10 items', self::validate($rows)['errors']['items']);
        self::assertArrayNotHasKey('items', self::validate(array_slice($rows, 0, 10))['errors']);
    }

    public function testErrorsAreReportedPerItem(): void
    {
        $errors = self::validate([self::row(), self::row(['shelf_code' => 'E-02', 'title' => '', 'size' => ''])])['errors'];
        self::assertSame(['title', 'size'], array_keys($errors['rows'][1]));
        self::assertArrayNotHasKey(0, $errors['rows']);
    }

    public function testRowsAreReadFromTheFormInOrder(): void
    {
        $post = ['items' => [
            '3' => ['title' => ' Coat ', 'shelf_code' => 'a-02', 'pending_photo' => '15'],
            '0' => ['title' => 'Shirt', 'size' => ['M']],
        ]];
        $files = ['photo' => [
            'name' => ['3' => 'coat.jpg'], 'tmp_name' => ['3' => '/tmp/php1'], 'size' => ['3' => 1000], 'error' => ['3' => UPLOAD_ERR_OK],
        ]];

        $rows = Intake::rowsFromRequest($post, $files);

        self::assertCount(2, $rows);
        self::assertSame('Coat', $rows[0]['title']);
        self::assertSame('a-02', $rows[0]['shelf_code']);
        self::assertSame('15', $rows[0]['pending_photo']);
        self::assertSame('/tmp/php1', $rows[0]['file']['tmp_name']);
        self::assertSame('', $rows[1]['size'], 'A non-text value is treated as empty.');
        self::assertNull($rows[1]['file']);
    }
}
