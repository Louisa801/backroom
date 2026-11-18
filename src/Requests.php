<?php
declare(strict_types=1);

/** M2/M3: try-on requests from the customer tablet. */
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
