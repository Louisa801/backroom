<?php
declare(strict_types=1);

/** M6: selling an item from a fitting room. */
final class Sales
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Transition in_fitting_room -> sold ("Sold"). Creates the sale at the item's sale
     * price, sets the outcome to 'sold' and closes the request when every item is handled.
     * A double tap or a second staff member is refused: the item is locked and must still
     * have no outcome. The database also allows only one sale per item.
     *
     * @return array{title: string, price: string, fitting_room: int, closed: bool}
     */
    public function sell(int $requestId, int $itemId): array
    {
        return transaction($this->pdo, function () use ($requestId, $itemId): array {
            $requests = new Requests($this->pdo);
            $item = $requests->lockFittingRoomItem($requestId, $itemId);

            $this->pdo->prepare('INSERT INTO sales (item_id, request_id, price) VALUES (:item, :request, :price)')
                ->execute(['item' => $itemId, 'request' => $requestId, 'price' => $item['sale_price']]);
            $this->pdo->prepare("UPDATE items SET status = 'sold' WHERE id = :id AND status = 'in_fitting_room'")
                ->execute(['id' => $itemId]);
            $this->pdo->prepare("UPDATE request_items SET outcome = 'sold', outcome_at = now() WHERE request_id = :request AND item_id = :item AND outcome IS NULL")
                ->execute(['request' => $requestId, 'item' => $itemId]);

            return [
                'title'        => $item['title'],
                'price'        => $item['sale_price'],
                'fitting_room' => (int) $item['fitting_room'],
                'closed'       => $requests->closeIfDone($requestId),
            ];
        });
    }
}
