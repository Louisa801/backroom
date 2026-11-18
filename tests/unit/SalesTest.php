<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** M6: "Sold" and "Back to archive" in a fitting room, and closing the request. */
final class SalesTest extends TestCase
{
    use RequestFixtures;

    private function sales(): Sales
    {
        return new Sales($this->pdo);
    }

    private function requests(): Requests
    {
        return new Requests($this->pdo);
    }

    private function salesFor(int $itemId): int
    {
        return (int) $this->value("SELECT count(*) FROM sales WHERE item_id = $itemId");
    }

    private function inArchive(int $itemId): bool
    {
        return in_array($itemId, array_column((new Items($this->pdo))->filterArchive([]), 'id'), true);
    }

    // ---------------- Sold ----------------

    public function testSoldCreatesOneSaleAtTheSalePrice(): void
    {
        $coat = $this->makeItem('Wool coat', price: '85.00');
        $shirt = $this->makeItem('Shirt');
        $id = $this->makeRequest([$coat, $shirt], 'ready', 2);

        $result = $this->sales()->sell($id, $coat);

        self::assertSame(['title' => 'Wool coat', 'price' => '85.00', 'fitting_room' => 2, 'closed' => false], $result);
        self::assertSame(1, $this->salesFor($coat));
        self::assertSame('85.00', $this->value("SELECT price FROM sales WHERE item_id = $coat"));
        self::assertNotFalse($this->value("SELECT sold_at FROM sales WHERE item_id = $coat AND sold_at IS NOT NULL"));
        self::assertSame('sold', $this->itemStatus($coat));
        self::assertSame('sold', $this->outcome($id, $coat));
        self::assertSame('ready', $this->requestStatus($id), 'The shirt is still undecided.');
    }

    public function testDoubleSoldIsRefusedAndThereIsStillOneSale(): void
    {
        $coat = $this->makeItem('Wool coat');
        $id = $this->makeRequest([$coat, $this->makeItem('Shirt')], 'ready');

        $this->sales()->sell($id, $coat);
        $this->assertRefused(fn () => $this->sales()->sell($id, $coat), 'already sold');

        self::assertSame(1, $this->salesFor($coat));
    }

    public function testSoldIsRefusedWhileTheRequestIsStillBeingFetched(): void
    {
        $coat = $this->makeItem('Wool coat');
        $id = $this->makeRequest([$coat], 'fetching');

        $this->assertRefused(fn () => $this->sales()->sell($id, $coat), 'not in a fitting room yet');
        self::assertSame(0, $this->salesFor($coat));
        self::assertSame('requested', $this->itemStatus($coat));
    }

    public function testSoldIsRefusedForAnItemOfAnotherRequest(): void
    {
        $coat = $this->makeItem('Wool coat');
        $this->makeRequest([$coat], 'ready');
        $other = $this->makeRequest([$this->makeItem('Shirt')], 'ready');

        $this->assertRefused(fn () => $this->sales()->sell($other, $coat), 'not part of request');
        self::assertSame(0, $this->salesFor($coat));
    }

    public function testSoldIsRefusedAfterBackToArchive(): void
    {
        $coat = $this->makeItem('Wool coat');
        $id = $this->makeRequest([$coat, $this->makeItem('Shirt')], 'ready');

        $this->requests()->returnItem($id, $coat);
        $this->assertRefused(fn () => $this->sales()->sell($id, $coat), 'already back in the archive');
        self::assertSame(0, $this->salesFor($coat));
        self::assertSame('in_archive', $this->itemStatus($coat));
    }

    public function testSoldIsRefusedWhenTheRequestIsClosed(): void
    {
        $coat = $this->makeItem('Wool coat');
        $id = $this->makeRequest([$coat], 'closed');

        $this->assertRefused(fn () => $this->sales()->sell($id, $coat), 'already back in the archive');
        self::assertSame(0, $this->salesFor($coat));
    }

    // ---------------- Back to archive ----------------

    public function testBackToArchivePutsTheItemBackOnTheTablet(): void
    {
        $coat = $this->makeItem('Wool coat');
        $id = $this->makeRequest([$coat, $this->makeItem('Shirt')], 'ready', 3);
        self::assertFalse($this->inArchive($coat));

        $result = $this->requests()->returnItem($id, $coat);

        self::assertSame('Wool coat', $result['title']);
        self::assertSame(3, $result['fitting_room']);
        self::assertFalse($result['closed']);
        self::assertMatchesRegularExpression('/^[A-Z]-\d{2}$/', $result['shelf_code']);
        self::assertSame('in_archive', $this->itemStatus($coat));
        self::assertSame('returned', $this->outcome($id, $coat));
        self::assertTrue($this->inArchive($coat));
        self::assertSame(0, $this->salesFor($coat));
    }

    public function testASoldItemNeverReturnsToTheArchive(): void
    {
        $coat = $this->makeItem('Wool coat');
        $id = $this->makeRequest([$coat, $this->makeItem('Shirt')], 'ready');

        $this->sales()->sell($id, $coat);
        $this->assertRefused(fn () => $this->requests()->returnItem($id, $coat), 'already sold');

        self::assertSame('sold', $this->itemStatus($coat));
        self::assertSame('sold', $this->outcome($id, $coat));
        self::assertFalse($this->inArchive($coat));
    }

    public function testDoubleBackToArchiveIsRefused(): void
    {
        $coat = $this->makeItem('Wool coat');
        $id = $this->makeRequest([$coat, $this->makeItem('Shirt')], 'ready');

        $this->requests()->returnItem($id, $coat);
        $this->assertRefused(fn () => $this->requests()->returnItem($id, $coat), 'already back in the archive');
    }

    public function testBackToArchiveIsRefusedBeforeTheItemIsInTheFittingRoom(): void
    {
        $coat = $this->makeItem('Wool coat');
        $id = $this->makeRequest([$coat], 'new');

        $this->assertRefused(fn () => $this->requests()->returnItem($id, $coat), 'not in a fitting room yet');
        self::assertSame('requested', $this->itemStatus($coat));
    }

    // ---------------- closing ----------------

    public function testRequestClosesOnlyWhenEveryItemIsHandled(): void
    {
        $a = $this->makeItem('A');
        $b = $this->makeItem('B');
        $c = $this->makeItem('C');
        $id = $this->makeRequest([$a, $b, $c], 'ready');

        self::assertFalse($this->sales()->sell($id, $a)['closed']);
        self::assertSame('ready', $this->requestStatus($id));
        self::assertFalse($this->requests()->returnItem($id, $b)['closed']);
        self::assertSame('ready', $this->requestStatus($id));
        self::assertFalse((bool) $this->value("SELECT closed_at IS NOT NULL FROM tryon_requests WHERE id = $id"));

        self::assertTrue($this->sales()->sell($id, $c)['closed'], 'The last outcome closes the request.');
        self::assertSame('closed', $this->requestStatus($id));
        self::assertTrue((bool) $this->value("SELECT closed_at IS NOT NULL FROM tryon_requests WHERE id = $id"));
    }

    public function testRequestClosesWhenTheLastItemGoesBackToTheArchive(): void
    {
        $a = $this->makeItem('A');
        $id = $this->makeRequest([$a], 'ready');

        self::assertTrue($this->requests()->returnItem($id, $a)['closed']);
        self::assertSame('closed', $this->requestStatus($id));
    }

    public function testAClosedRequestLeavesTheFittingRoom(): void
    {
        $a = $this->makeItem('A');
        $id = $this->makeRequest([$a], 'ready', 1);

        $this->sales()->sell($id, $a);

        self::assertNotContains($id, array_column($this->requests()->fittingRooms()[1], 'id'));
    }
}
