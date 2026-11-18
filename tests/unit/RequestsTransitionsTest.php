<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** M4: new -> fetching -> ready, and the data for the staff screen. */
final class RequestsTransitionsTest extends TestCase
{
    use RequestFixtures;

    private function requests(): Requests
    {
        return new Requests($this->pdo);
    }

    // ---------------- new -> fetching ----------------

    public function testStartFetchingMovesANewRequestOn(): void
    {
        $item = $this->makeItem('Coat');
        $id = $this->makeRequest([$item], 'new', 2);

        self::assertSame(['status' => 'fetching', 'fitting_room' => 2], $this->requests()->startFetching($id));
        self::assertSame('fetching', $this->requestStatus($id));
        self::assertSame('requested', $this->itemStatus($item), 'Items stay requested while being fetched.');
    }

    #[DataProvider('notNew')]
    public function testStartFetchingIsRefusedUnlessNew(string $status, string $message): void
    {
        $id = $this->makeRequest([$this->makeItem('Coat')], $status);

        $this->assertRefused(fn () => $this->requests()->startFetching($id), $message);
        self::assertSame($status, $this->requestStatus($id));
    }

    public static function notNew(): array
    {
        return [
            'already fetching' => ['fetching', 'already being fetched'],
            'already ready'    => ['ready', 'already in fitting room'],
            'already closed'   => ['closed', 'already closed'],
        ];
    }

    public function testUnknownRequestIsRefused(): void
    {
        $this->assertRefused(fn () => $this->requests()->startFetching(999999999), "doesn't exist");
    }

    // ---------------- fetching -> ready ----------------

    public function testMarkReadyMovesItemsIntoTheFittingRoom(): void
    {
        $a = $this->makeItem('Coat');
        $b = $this->makeItem('Shirt');
        $id = $this->makeRequest([$a, $b], 'fetching', 3);

        self::assertSame(['status' => 'ready', 'fitting_room' => 3], $this->requests()->markReady($id));

        self::assertSame('ready', $this->requestStatus($id));
        self::assertNotFalse($this->value("SELECT ready_at FROM tryon_requests WHERE id = $id AND ready_at IS NOT NULL"));
        self::assertSame('in_fitting_room', $this->itemStatus($a));
        self::assertSame('in_fitting_room', $this->itemStatus($b));
    }

    public function testANewRequestCannotSkipFetching(): void
    {
        $item = $this->makeItem('Coat');
        $id = $this->makeRequest([$item], 'new');

        $this->assertRefused(fn () => $this->requests()->markReady($id), 'has to be fetched first');
        self::assertSame('new', $this->requestStatus($id));
        self::assertSame('requested', $this->itemStatus($item));
    }

    #[DataProvider('readyOrClosed')]
    public function testMarkReadyIsRefusedWhenAlreadyPastIt(string $status, string $message): void
    {
        $id = $this->makeRequest([$this->makeItem('Coat')], $status);
        $this->assertRefused(fn () => $this->requests()->markReady($id), $message);
        self::assertSame($status, $this->requestStatus($id));
    }

    public static function readyOrClosed(): array
    {
        return [['ready', 'already in fitting room'], ['closed', 'already closed']];
    }

    public function testMarkReadyChangesNothingIfAnItemIsNoLongerRequested(): void
    {
        $a = $this->makeItem('Coat');
        $b = $this->makeItem('Shirt');
        $id = $this->makeRequest([$a, $b], 'fetching');
        $this->pdo->exec("UPDATE items SET status = 'in_archive' WHERE id = $b"); // inconsistent on purpose

        $this->assertRefused(fn () => $this->requests()->markReady($id), 'no longer waiting');
        self::assertSame('fetching', $this->requestStatus($id));
        self::assertSame('requested', $this->itemStatus($a), 'The other item did not move either.');
    }

    // ---------------- the one-tap button ----------------

    public function testAdvanceTakesOneStepPerTap(): void
    {
        $id = $this->makeRequest([$this->makeItem('Coat')], 'new');

        self::assertSame('fetching', $this->requests()->advance($id, 'new')['status']);
        self::assertSame('ready', $this->requests()->advance($id, 'fetching')['status']);
    }

    public function testDoubleTapIsRefused(): void
    {
        $id = $this->makeRequest([$this->makeItem('Coat')], 'new');

        $this->requests()->advance($id, 'new');
        // The second tap still says "new": the request has moved on, so nothing happens.
        $this->assertRefused(fn () => $this->requests()->advance($id, 'new'), 'already being fetched');
        self::assertSame('fetching', $this->requestStatus($id));
    }

    public function testAdvanceFromReadyOrUnknownIsRefused(): void
    {
        $id = $this->makeRequest([$this->makeItem('Coat')], 'ready');
        $this->assertRefused(fn () => $this->requests()->advance($id, 'ready'), 'no next step');
        $this->assertRefused(fn () => $this->requests()->advance($id, 'sold'), 'no next step');
        self::assertSame('ready', $this->requestStatus($id));
    }

    // ---------------- staff screen data ----------------

    public function testQueueHasOnlyOpenRequestsOldestFirst(): void
    {
        $newer = $this->makeRequest([$this->makeItem('Newer')], 'new', 1, '2 minutes');
        $older = $this->makeRequest([$this->makeItem('Older a'), $this->makeItem('Older b')], 'fetching', 2, '30 minutes');
        $ready = $this->makeRequest([$this->makeItem('Ready')], 'ready');
        $closed = $this->makeRequest([$this->makeItem('Closed')], 'closed');

        $queue = array_values(array_filter($this->requests()->queue(), fn ($r) => in_array($r['id'], [$newer, $older, $ready, $closed], true)));

        self::assertSame([$older, $newer], array_column($queue, 'id'));
        self::assertSame(['fetching', 'new'], array_column($queue, 'status'));
        self::assertSame(2, $queue[0]['fitting_room']);
        self::assertGreaterThanOrEqual(29, $queue[0]['waiting_minutes']);
        self::assertSame(['Older a', 'Older b'], array_column($queue[0]['items'], 'title'));
        self::assertMatchesRegularExpression('/^[A-Z]-\d{2}$/', $queue[0]['items'][0]['shelf_code']);
    }

    public function testFittingRoomsShowReadyRequestsPerRoom(): void
    {
        $room2 = $this->makeRequest([$this->makeItem('In room 2')], 'ready', 2);
        $this->makeRequest([$this->makeItem('Still fetching')], 'fetching', 3);

        $rooms = $this->requests()->fittingRooms();

        self::assertSame([1, 2, 3], array_keys($rooms));
        $ids = array_column($rooms[2], 'id');
        self::assertContains($room2, $ids);
        foreach ($rooms as $requests) {
            foreach ($requests as $request) {
                self::assertSame('ready', $request['status']);
            }
        }
        $mine = array_values(array_filter($rooms[2], fn ($r) => $r['id'] === $room2))[0];
        self::assertSame('In room 2', $mine['items'][0]['title']);
        self::assertNull($mine['items'][0]['outcome']);
    }
}
