<?php
declare(strict_types=1);

/**
 * A status change that is not allowed from the current status (double tap, two staff
 * members at once, a skipped step). Nothing was changed; the message can be shown as is.
 */
final class TransitionRefusedException extends RuntimeException
{
}

/** M2/M3: try-on requests from the customer tablet. M4/M6: staff moving them on. */
final class Requests
{
    public const MAX_ITEMS = 5;
    public const FITTING_ROOMS = [1, 2, 3];
    public const STATUSES = ['new', 'fetching', 'ready', 'closed'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Validate what the tablet sends.
     *
     * @return array{ids: list<int>, room: int}
     * @throws InvalidArgumentException with a message that can be shown to the customer
     */
    public static function normalise(mixed $itemIds, mixed $room): array
    {
        if (!is_array($itemIds)) {
            throw new InvalidArgumentException('Add at least one item to your try-on list.');
        }

        $ids = [];
        foreach ($itemIds as $id) {
            if (is_int($id) && $id > 0) {
                $ids[$id] = $id;
            } elseif (is_string($id) && ctype_digit($id) && strlen($id) <= 9 && (int) $id > 0) {
                $ids[(int) $id] = (int) $id;
            } else {
                throw new InvalidArgumentException('Something on your try-on list is not a valid item. Please clear the list and try again.');
            }
        }
        $ids = array_values($ids);

        if ($ids === []) {
            throw new InvalidArgumentException('Add at least one item to your try-on list.');
        }
        if (count($ids) > self::MAX_ITEMS) {
            throw new InvalidArgumentException('A try-on list can have at most ' . self::MAX_ITEMS . ' items.');
        }

        $roomNumber = match (true) {
            is_int($room)                                 => $room,
            is_string($room) && ctype_digit($room) && strlen($room) <= 2 => (int) $room,
            default                                       => 0,
        };
        if (!in_array($roomNumber, self::FITTING_ROOMS, true)) {
            throw new InvalidArgumentException('Please choose fitting room 1, 2 or 3.');
        }

        return ['ids' => $ids, 'room' => $roomNumber];
    }

    /**
     * Transition in_archive -> requested. Creates a request with every item that is still
     * in the archive; the others are reported as taken. Two tablets can never get the same
     * item: the rows are locked (in id order, so two requests can't deadlock) and the
     * status is checked while they are locked.
     *
     * @return array{request_id: ?int, requested: list<array{id: int, title: string}>, taken: list<array{id: int, title: ?string}>}
     *   request_id is null when nothing was available (then nothing is changed)
     */
    public function create(mixed $itemIds, mixed $room): array
    {
        ['ids' => $ids, 'room' => $room] = self::normalise($itemIds, $room);
        $idArray = '{' . implode(',', $ids) . '}';

        // Uses a savepoint when the caller already has a transaction open (the tests do).
        $nested = $this->pdo->inTransaction();
        $nested ? $this->pdo->exec('SAVEPOINT request_create') : $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, title, status FROM items WHERE id = ANY(CAST(:ids AS int[])) ORDER BY id FOR UPDATE'
            );
            $stmt->execute(['ids' => $idArray]);
            $found = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $found[(int) $row['id']] = $row;
            }

            $requested = [];
            $taken = [];
            foreach ($ids as $id) {
                $row = $found[$id] ?? null;
                if ($row !== null && $row['status'] === 'in_archive') {
                    $requested[] = ['id' => $id, 'title' => $row['title']];
                } else {
                    $taken[] = ['id' => $id, 'title' => $row['title'] ?? null];
                }
            }

            if ($requested === []) {
                $nested ? $this->pdo->exec('ROLLBACK TO SAVEPOINT request_create') : $this->pdo->rollBack();
                return ['request_id' => null, 'requested' => [], 'taken' => $taken];
            }

            $stmt = $this->pdo->prepare("INSERT INTO tryon_requests (fitting_room, status) VALUES (:room, 'new') RETURNING id");
            $stmt->execute(['room' => $room]);
            $requestId = (int) $stmt->fetchColumn();

            $stmt = $this->pdo->prepare('INSERT INTO request_items (request_id, item_id) VALUES (:request, :item)');
            foreach ($requested as $item) {
                $stmt->execute(['request' => $requestId, 'item' => $item['id']]);
            }

            $requestedIds = '{' . implode(',', array_column($requested, 'id')) . '}';
            $stmt = $this->pdo->prepare(
                "UPDATE items SET status = 'requested' WHERE id = ANY(CAST(:ids AS int[])) AND status = 'in_archive'"
            );
            $stmt->execute(['ids' => $requestedIds]);
            if ($stmt->rowCount() !== count($requested)) {
                throw new RuntimeException('An item changed while it was locked.');
            }

            $nested ? $this->pdo->exec('RELEASE SAVEPOINT request_create') : $this->pdo->commit();
            return ['request_id' => $requestId, 'requested' => $requested, 'taken' => $taken];
        } catch (Throwable $e) {
            $nested ? $this->pdo->exec('ROLLBACK TO SAVEPOINT request_create') : $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * A request for the status page and the polling endpoint.
     *
     * @return array{id: int, status: string, label: string, fitting_room: int, items: list<array{title: string, size: string, photo_url: string}>}|null
     */
    public function status(int $requestId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, status, fitting_room FROM tryon_requests WHERE id = :id');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($request === false) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT i.title, i.size, i.photo_id, i.photo_path
             FROM request_items ri JOIN items i ON i.id = ri.item_id
             WHERE ri.request_id = :id ORDER BY i.id'
        );
        $stmt->execute(['id' => $requestId]);
        $items = array_map(static fn (array $r): array => [
            'title'     => $r['title'],
            'size'      => $r['size'],
            'photo_url' => Items::photoUrl($r['photo_id'], $r['photo_path']),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $room = (int) $request['fitting_room'];
        return [
            'id'           => (int) $request['id'],
            'status'       => $request['status'],
            'label'        => self::label($request['status'], $room),
            'fitting_room' => $room,
            'items'        => $items,
        ];
    }

    /** What the customer reads for each status. */
    public static function label(string $status, int $room): string
    {
        return match ($status) {
            'new'      => 'Request sent. Staff will start fetching your items soon.',
            'fetching' => 'Staff are fetching your items now.',
            'ready'    => "Ready in fitting room $room. You can go there now.",
            'closed'   => 'This request is finished. Thank you!',
            default    => 'Status unknown.',
        };
    }

    // ------------------------------------------------------------------
    // M4: fetch queue transitions (new -> fetching -> ready)
    // ------------------------------------------------------------------

    /**
     * The one-tap button: move the request one step on from the status the screen showed.
     * A double tap or a second staff member finds the request already moved and is refused.
     *
     * @return array{status: string, fitting_room: int} the new status
     */
    public function advance(int $requestId, string $from): array
    {
        return match ($from) {
            'new'      => $this->startFetching($requestId),
            'fetching' => $this->markReady($requestId),
            default    => throw new TransitionRefusedException('Nothing changed: there is no next step from here.'),
        };
    }

    /** Transition new -> fetching. @return array{status: string, fitting_room: int} */
    public function startFetching(int $requestId): array
    {
        return transaction($this->pdo, function () use ($requestId): array {
            $request = $this->lockRequest($requestId);
            if ($request['status'] !== 'new') {
                throw new TransitionRefusedException(self::alreadyMessage($request));
            }
            $this->pdo->prepare("UPDATE tryon_requests SET status = 'fetching' WHERE id = :id AND status = 'new'")
                ->execute(['id' => $requestId]);
            return ['status' => 'fetching', 'fitting_room' => (int) $request['fitting_room']];
        });
    }

    /**
     * Transition fetching -> ready: the items are in the fitting room.
     * Every item must still be 'requested'; otherwise nothing changes.
     *
     * @return array{status: string, fitting_room: int}
     */
    public function markReady(int $requestId): array
    {
        return transaction($this->pdo, function () use ($requestId): array {
            $request = $this->lockRequest($requestId);
            if ($request['status'] !== 'fetching') {
                throw new TransitionRefusedException(
                    $request['status'] === 'new'
                        ? "Nothing changed: request #$requestId has to be fetched first."
                        : self::alreadyMessage($request)
                );
            }

            $stmt = $this->pdo->prepare(
                'SELECT i.id, i.status FROM request_items ri JOIN items i ON i.id = ri.item_id
                 WHERE ri.request_id = :id ORDER BY i.id FOR UPDATE OF i'
            );
            $stmt->execute(['id' => $requestId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                if ($item['status'] !== 'requested') {
                    throw new TransitionRefusedException("Nothing changed: an item of request #$requestId is no longer waiting to be fetched.");
                }
            }

            $stmt = $this->pdo->prepare(
                "UPDATE items SET status = 'in_fitting_room'
                 WHERE id IN (SELECT item_id FROM request_items WHERE request_id = :id) AND status = 'requested'"
            );
            $stmt->execute(['id' => $requestId]);
            if ($stmt->rowCount() !== count($items)) {
                throw new RuntimeException('An item changed while it was locked.');
            }

            $this->pdo->prepare("UPDATE tryon_requests SET status = 'ready', ready_at = now() WHERE id = :id AND status = 'fetching'")
                ->execute(['id' => $requestId]);
            return ['status' => 'ready', 'fitting_room' => (int) $request['fitting_room']];
        });
    }

    // ------------------------------------------------------------------
    // M6: fitting room outcomes (Sold is in Sales.php)
    // ------------------------------------------------------------------

    /**
     * Transition in_fitting_room -> in_archive ("Back to archive"), outcome 'returned'.
     * A sold item is refused, so it can never come back to the archive.
     *
     * @return array{title: string, shelf_code: string, fitting_room: int, closed: bool}
     */
    public function returnItem(int $requestId, int $itemId): array
    {
        return transaction($this->pdo, function () use ($requestId, $itemId): array {
            $item = $this->lockFittingRoomItem($requestId, $itemId);

            $stmt = $this->pdo->prepare("UPDATE items SET status = 'in_archive' WHERE id = :id AND status = 'in_fitting_room'");
            $stmt->execute(['id' => $itemId]);
            $stmt = $this->pdo->prepare(
                "UPDATE request_items SET outcome = 'returned', outcome_at = now() WHERE request_id = :request AND item_id = :item AND outcome IS NULL"
            );
            $stmt->execute(['request' => $requestId, 'item' => $itemId]);

            return [
                'title'        => $item['title'],
                'shelf_code'   => $item['shelf_code'],
                'fitting_room' => (int) $item['fitting_room'],
                'closed'       => $this->closeIfDone($requestId),
            ];
        });
    }

    /**
     * Lock a request item that is about to get an outcome and check it may get one:
     * the request is ready, the item has no outcome yet and is in the fitting room.
     * Must be called inside a transaction (Sales::sell and returnItem do).
     *
     * @return array{title: string, shelf_code: string, sale_price: string, fitting_room: int}
     */
    public function lockFittingRoomItem(int $requestId, int $itemId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.status AS request_status, r.fitting_room, ri.outcome, i.status AS item_status,
                    i.title, i.shelf_code, i.sale_price
             FROM request_items ri
             JOIN tryon_requests r ON r.id = ri.request_id
             JOIN items i ON i.id = ri.item_id
             WHERE ri.request_id = :request AND ri.item_id = :item
             FOR UPDATE'
        );
        $stmt->execute(['request' => $requestId, 'item' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new TransitionRefusedException("Nothing changed: this item is not part of request #$requestId.");
        }
        if ($row['outcome'] === 'sold') {
            throw new TransitionRefusedException("Nothing changed: {$row['title']} is already sold.");
        }
        if ($row['outcome'] === 'returned') {
            throw new TransitionRefusedException("Nothing changed: {$row['title']} is already back in the archive.");
        }
        if ($row['request_status'] !== 'ready') {
            throw new TransitionRefusedException("Nothing changed: request #$requestId is not in a fitting room yet.");
        }
        if ($row['item_status'] !== 'in_fitting_room') {
            throw new TransitionRefusedException("Nothing changed: {$row['title']} is not in the fitting room.");
        }
        return $row;
    }

    /**
     * Transition ready -> closed once every item has an outcome. Call inside the
     * transaction that set the last outcome. Returns true if the request was closed.
     */
    public function closeIfDone(int $requestId): bool
    {
        $stmt = $this->pdo->prepare('SELECT count(*) FROM request_items WHERE request_id = :id AND outcome IS NULL');
        $stmt->execute(['id' => $requestId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }
        $stmt = $this->pdo->prepare("UPDATE tryon_requests SET status = 'closed', closed_at = now() WHERE id = :id AND status = 'ready'");
        $stmt->execute(['id' => $requestId]);
        return $stmt->rowCount() === 1;
    }

    // ------------------------------------------------------------------
    // Staff screen data
    // ------------------------------------------------------------------

    /**
     * M4: open requests (new and fetching), oldest first.
     *
     * @return list<array{id: int, status: string, fitting_room: int, waiting_minutes: int, items: list<array>}>
     */
    public function queue(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, status, fitting_room, floor(extract(epoch FROM now() - created_at) / 60)::int AS waiting_minutes
             FROM tryon_requests WHERE status IN ('new', 'fetching') ORDER BY created_at, id"
        )->fetchAll(PDO::FETCH_ASSOC);

        return $this->withItems($rows);
    }

    /**
     * M6: ready requests per fitting room (1-3), each item with its outcome so far.
     *
     * @return array<int, list<array{id: int, status: string, fitting_room: int, items: list<array>}>>
     */
    public function fittingRooms(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, status, fitting_room FROM tryon_requests WHERE status = 'ready' ORDER BY ready_at, id"
        )->fetchAll(PDO::FETCH_ASSOC);

        $rooms = array_fill_keys(self::FITTING_ROOMS, []);
        foreach ($this->withItems($rows) as $request) {
            $rooms[$request['fitting_room']][] = $request;
        }
        return $rooms;
    }

    /** Add the items (sorted by shelf code, the order staff walk the shelves) to each request. */
    private function withItems(array $requests): array
    {
        if ($requests === []) {
            return [];
        }
        $ids = '{' . implode(',', array_map(static fn (array $r): int => (int) $r['id'], $requests)) . '}';
        $stmt = $this->pdo->prepare(
            'SELECT ri.request_id, i.id, i.title, i.size, i.shelf_code, i.sale_price, i.photo_id, i.photo_path, ri.outcome
             FROM request_items ri JOIN items i ON i.id = ri.item_id
             WHERE ri.request_id = ANY(CAST(:ids AS int[]))
             ORDER BY i.shelf_code, i.id'
        );
        $stmt->execute(['ids' => $ids]);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[(int) $r['request_id']][] = [
                'id'         => (int) $r['id'],
                'title'      => $r['title'],
                'size'       => $r['size'],
                'shelf_code' => $r['shelf_code'],
                'sale_price' => $r['sale_price'],
                'photo_url'  => Items::photoUrl($r['photo_id'], $r['photo_path']),
                'outcome'    => $r['outcome'],
            ];
        }

        return array_map(static fn (array $r): array => [
            'id'           => (int) $r['id'],
            'status'       => $r['status'],
            'fitting_room' => (int) $r['fitting_room'],
        ] + (isset($r['waiting_minutes']) ? ['waiting_minutes' => (int) $r['waiting_minutes']] : [])
          + ['items' => $items[(int) $r['id']] ?? []], $requests);
    }

    /** @return array{id: int, status: string, fitting_room: int} the request, locked */
    private function lockRequest(int $requestId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, status, fitting_room FROM tryon_requests WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($request === false) {
            throw new TransitionRefusedException("Nothing changed: request #$requestId doesn't exist.");
        }
        return $request;
    }

    private static function alreadyMessage(array $request): string
    {
        $id = $request['id'];
        return match ($request['status']) {
            'new'      => "Nothing changed: request #$id is still waiting to be fetched.",
            'fetching' => "Nothing changed: request #$id is already being fetched.",
            'ready'    => "Nothing changed: request #$id is already in fitting room {$request['fitting_room']}.",
            'closed'   => "Nothing changed: request #$id is already closed.",
            default    => "Nothing changed: request #$id can't move on from here.",
        };
    }

    /** "Striped top, Wool coat and 1 more item": titles of taken items for a message. */
    public static function describeTaken(array $taken): string
    {
        $titles = array_values(array_filter(array_column($taken, 'title'), 'is_string'));
        $unknown = count($taken) - count($titles);
        if ($unknown > 0) {
            $titles[] = $unknown === 1 ? 'an item that is no longer in the archive' : "$unknown items that are no longer in the archive";
        }
        if (count($titles) <= 1) {
            return $titles[0] ?? '';
        }
        $last = array_pop($titles);
        return implode(', ', $titles) . ' and ' . $last;
    }
}
