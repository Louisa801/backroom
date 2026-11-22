<?php
declare(strict_types=1);

/** Reading items from the archive. */
final class Items
{
    /** Same lists as the CHECK constraints in sql/schema.sql. */
    public const SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    public const COLOURS = ['black', 'white', 'grey', 'navy', 'blue', 'green', 'red', 'pink', 'beige', 'brown'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Validate filter input (e.g. $_GET). Empty or missing values mean "no filter".
     *
     * @return array{size?: string, category?: int, colour?: string, max_price?: string}
     * @throws InvalidArgumentException with a message that can be shown to the user
     */
    public static function normaliseFilters(array $query): array
    {
        $filters = [];

        $size = self::text($query, 'size');
        if ($size !== '') {
            if (!in_array($size, self::SIZES, true)) {
                throw new InvalidArgumentException('Please choose a size from the list.');
            }
            $filters['size'] = $size;
        }

        $category = self::text($query, 'category');
        if ($category !== '') {
            if (!ctype_digit($category) || (int) $category < 1) {
                throw new InvalidArgumentException('Please choose a category from the list.');
            }
            $filters['category'] = (int) $category;
        }

        $colour = self::text($query, 'colour');
        if ($colour !== '') {
            if (!in_array($colour, self::COLOURS, true)) {
                throw new InvalidArgumentException('Please choose a colour from the list.');
            }
            $filters['colour'] = $colour;
        }

        $maxPrice = self::text($query, 'max_price');
        if ($maxPrice !== '') {
            if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $maxPrice) || (float) $maxPrice <= 0) {
                throw new InvalidArgumentException('Max price must be a positive amount in euros.');
            }
            $filters['max_price'] = number_format((float) $maxPrice, 2, '.', '');
        }

        return $filters;
    }

    /**
     * Items a customer may see: status in_archive only, filtered, newest buy-in first.
     *
     * @param array{size?: string, category?: int, colour?: string, max_price?: string} $filters from normaliseFilters()
     * @return list<array{id: int, title: string, size: string, colour: string, category: string, sale_price: string, photo_url: string}>
     */
    public function filterArchive(array $filters): array
    {
        $sql = "SELECT i.id, i.title, i.size, i.colour, c.name AS category, i.sale_price, i.photo_id, i.photo_path
                FROM items i
                JOIN categories c ON c.id = i.category_id
                JOIN buy_ins b ON b.id = i.buy_in_id
                WHERE i.status = 'in_archive'";
        $params = [];

        if (isset($filters['size'])) {
            $sql .= ' AND i.size = :size';
            $params['size'] = $filters['size'];
        }
        if (isset($filters['category'])) {
            $sql .= ' AND i.category_id = :category';
            $params['category'] = $filters['category'];
        }
        if (isset($filters['colour'])) {
            $sql .= ' AND i.colour = :colour';
            $params['colour'] = $filters['colour'];
        }
        if (isset($filters['max_price'])) {
            $sql .= ' AND i.sale_price <= :max_price';
            $params['max_price'] = $filters['max_price'];
        }

        $sql .= ' ORDER BY b.created_at DESC, i.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn (array $row): array => [
            'id'         => (int) $row['id'],
            'title'      => $row['title'],
            'size'       => $row['size'],
            'colour'     => $row['colour'],
            'category'   => $row['category'],
            'sale_price' => $row['sale_price'],
            'photo_url'  => self::photoUrl($row['photo_id'], $row['photo_path']),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * One item for the customer detail page, only while it is in the archive.
     * Requested, in a fitting room, sold or unknown: null. Never includes the shelf code.
     *
     * @return array{id: int, title: string, category: string, size: string, colour: string, condition: string, sale_price: string, photo_url: string}|null
     */
    public function findInArchive(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.id, i.title, c.name AS category, i.size, i.colour, i.condition, i.sale_price, i.photo_id, i.photo_path
             FROM items i JOIN categories c ON c.id = i.category_id
             WHERE i.id = :id AND i.status = 'in_archive'"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return [
            'id'         => (int) $row['id'],
            'title'      => $row['title'],
            'category'   => $row['category'],
            'size'       => $row['size'],
            'colour'     => $row['colour'],
            'condition'  => $row['condition'],
            'sale_price' => $row['sale_price'],
            'photo_url'  => self::photoUrl($row['photo_id'], $row['photo_path']),
        ];
    }

    /** The query string of valid filters, e.g. "size=M&colour=navy" (for links that keep the filters). */
    public static function filterQuery(array $filters): string
    {
        return http_build_query(array_intersect_key($filters, array_flip(['size', 'category', 'colour', 'max_price'])));
    }

    /** URL of an item photo: uploaded photos come from the database, seed photos are static files. */
    public static function photoUrl(int|string|null $photoId, ?string $photoPath): string
    {
        if ($photoId !== null && $photoId !== '') {
            return '/photo.php?id=' . (int) $photoId;
        }
        return '/' . ltrim((string) $photoPath, '/');
    }

    /** @return list<array{id: int, name: string}> */
    public function categories(): array
    {
        $rows = $this->pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => $r['name']], $rows);
    }

    private static function text(array $query, string $key): string
    {
        $value = $query[$key] ?? '';
        return is_string($value) ? trim($value) : '__invalid__';
    }
}
