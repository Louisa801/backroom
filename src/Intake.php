<?php
declare(strict_types=1);

/** Thrown by Intake::save() when another screen took a shelf code in the meantime. */
final class ShelfCodeTakenException extends RuntimeException
{
    /** @param list<string> $codes */
    public function __construct(public readonly array $codes)
    {
        parent::__construct('Shelf code(s) already taken: ' . implode(', ', $codes));
    }
}

/** M5: buy-in from a walk-in seller. */
final class Intake
{
    public const MAX_ITEMS = 10;
    public const TITLE_MAX = 100;
    public const STORE_CREDIT_PERCENT = 120;      // store credit = cash x 1.2
    public const MAX_PRICE_CENTS = 99_999_999;    // NUMERIC(8,2) holds up to 999999.99

    public const CONDITIONS = ['like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'];
    public const PAYOUT_TYPES = ['cash' => 'Cash', 'store_credit' => 'Store credit'];

    /** Text fields of one item row, in form order. */
    public const FIELDS = ['title', 'category', 'size', 'colour', 'condition', 'purchase_price', 'sale_price', 'shelf_code'];

    public function __construct(private PDO $pdo)
    {
    }

    // ------------------------------------------------------------------
    // Payout
    // ------------------------------------------------------------------

    /**
     * Cash = sum of the purchase prices. Store credit = cash x 1.2, rounded half up to the cent.
     * Works in whole cents so there are no floating-point errors.
     *
     * @param list<int> $purchaseCents
     * @return array{cash: int, payout: int} in cents
     */
    public static function payout(array $purchaseCents, string $payoutType): array
    {
        if (!isset(self::PAYOUT_TYPES[$payoutType])) {
            throw new InvalidArgumentException('Unknown payout type.');
        }
        $cash = array_sum($purchaseCents);
        $payout = $payoutType === 'store_credit'
            ? intdiv($cash * self::STORE_CREDIT_PERCENT + 50, 100)
            : $cash;

        return ['cash' => $cash, 'payout' => $payout];
    }

    /** "12", "12.5", "12,50" -> 1250. Null if it is not a plain amount. */
    public static function parsePrice(string $input): ?int
    {
        $value = str_replace(',', '.', trim($input));
        if (!preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/', $value, $m)) {
            return null;
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /** 1250 -> "12.50" (for the database). */
    public static function centsToDecimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    // ------------------------------------------------------------------
    // Shelf codes
    // ------------------------------------------------------------------

    /**
     * The first free shelf spots in order A-01 ... A-99, B-01 ... Z-99.
     *
     * @param list<string> $taken codes of unsold items
     * @return list<string>
     */
    public static function suggestShelfCodes(array $taken, int $count = 1): array
    {
        $takenSet = array_fill_keys(array_map('strtoupper', $taken), true);
        $free = [];
        foreach (range('A', 'Z') as $rack) {
            for ($spot = 1; $spot <= 99; $spot++) {
                $code = sprintf('%s-%02d', $rack, $spot);
                if (!isset($takenSet[$code])) {
                    $free[] = $code;
                    if (count($free) === $count) {
                        return $free;
                    }
                }
            }
        }
        throw new RuntimeException('There are not enough free shelf spots left.');
    }

    /** Shelf codes in use by items that are not sold. @return list<string> */
    public function takenShelfCodes(): array
    {
        return $this->pdo->query("SELECT shelf_code FROM items WHERE status <> 'sold' ORDER BY shelf_code")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    // ------------------------------------------------------------------
    // Reading the form
    // ------------------------------------------------------------------

    /**
     * Item rows from the POST data, in the order they appear on the form.
     * Each row has the text fields as strings, plus 'pending_photo' and 'file' (its $_FILES entry or null).
     *
     * @return list<array<string, mixed>>
     */
    public static function rowsFromRequest(array $post, array $files): array
    {
        $items = $post['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $rows = [];
        foreach ($items as $key => $input) {
            if (!is_array($input)) {
                continue;
            }
            $row = [];
            foreach (self::FIELDS as $field) {
                $row[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
            }
            $row['pending_photo'] = is_string($input['pending_photo'] ?? null) ? $input['pending_photo'] : '';
            $row['file'] = self::fileFor($files, $key);
            $rows[] = $row;
        }
        return $rows;
    }

    /** One entry of a PHP multi-file upload field named photo[key]. */
    private static function fileFor(array $files, int|string $key): ?array
    {
        $photo = $files['photo'] ?? null;
        if (!is_array($photo) || !isset($photo['error'][$key]) || is_array($photo['error'][$key])) {
            return null;
        }
        return [
            'name'     => (string) ($photo['name'][$key] ?? ''),
            'tmp_name' => (string) ($photo['tmp_name'][$key] ?? ''),
            'size'     => (int) ($photo['size'][$key] ?? 0),
            'error'    => (int) $photo['error'][$key],
        ];
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Check a buy-in. Photos are handled before this: each row carries 'photo_id' (or null)
     * and 'photo_error' (or null).
     *
     * @param list<array<string, mixed>> $rows from rowsFromRequest(), plus photo_id / photo_error
     * @param list<int> $categoryIds
     * @param list<string> $takenShelfCodes codes of unsold items
     * @return array{data: array, errors: array} errors is empty when the buy-in is valid:
     *   ['payout_type' => msg, 'items' => msg, 'rows' => [index => [field => msg]]]
     */
    public static function validate(array $rows, string $payoutType, array $categoryIds, array $takenShelfCodes): array
    {
        $errors = [];
        $data = ['payout_type' => $payoutType, 'items' => []];

        if (!isset(self::PAYOUT_TYPES[$payoutType])) {
            $errors['payout_type'] = 'Please choose cash or store credit.';
        }
        if (count($rows) === 0) {
            $errors['items'] = 'Add at least one item.';
        } elseif (count($rows) > self::MAX_ITEMS) {
            $errors['items'] = 'A buy-in can have at most ' . self::MAX_ITEMS . ' items. Please split it into two buy-ins.';
        }

        $takenSet = array_fill_keys(array_map('strtoupper', $takenShelfCodes), true);
        $usedInForm = [];

        foreach (array_values($rows) as $i => $row) {
            [$item, $rowErrors] = self::validateRow($row, $categoryIds);

            // Shelf code: free in the shop and not used twice in this buy-in.
            if (!isset($rowErrors['shelf_code'])) {
                $code = $item['shelf_code'];
                if (isset($takenSet[$code])) {
                    $rowErrors['shelf_code'] = "Shelf $code is already taken by another item. Please choose another spot.";
                } elseif (isset($usedInForm[$code])) {
                    $rowErrors['shelf_code'] = "Shelf $code is already used for item " . ($usedInForm[$code] + 1) . '. Each item needs its own spot.';
                } else {
                    $usedInForm[$code] = $i;
                }
            }

            if ($rowErrors !== []) {
                $errors['rows'][$i] = $rowErrors;
            }
            $data['items'][] = $item;
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /** @return array{0: array, 1: array<string, string>} cleaned item and its errors */
    private static function validateRow(array $row, array $categoryIds): array
    {
        $errors = [];
        $item = [];

        $title = (string) ($row['title'] ?? '');
        if ($title === '') {
            $errors['title'] = 'Please enter a title.';
        } elseif (mb_strlen($title) > self::TITLE_MAX) {
            $errors['title'] = 'The title can be at most ' . self::TITLE_MAX . ' characters.';
        }
        $item['title'] = $title;

        $category = (string) ($row['category'] ?? '');
        if (!ctype_digit($category) || !in_array((int) $category, $categoryIds, true)) {
            $errors['category'] = 'Please choose a category.';
        }
        $item['category_id'] = (int) $category;

        $size = (string) ($row['size'] ?? '');
        if (!in_array($size, Items::SIZES, true)) {
            $errors['size'] = 'Please choose a size.';
        }
        $item['size'] = $size;

        $colour = (string) ($row['colour'] ?? '');
        if (!in_array($colour, Items::COLOURS, true)) {
            $errors['colour'] = 'Please choose a colour.';
        }
        $item['colour'] = $colour;

        $condition = (string) ($row['condition'] ?? '');
        if (!isset(self::CONDITIONS[$condition])) {
            $errors['condition'] = 'Please choose the condition.';
        }
        $item['condition'] = $condition;

        [$purchase, $purchaseError] = self::checkPrice((string) ($row['purchase_price'] ?? ''), 'purchase price');
        if ($purchaseError !== null) {
            $errors['purchase_price'] = $purchaseError;
        }
        $item['purchase_cents'] = $purchase;

        [$sale, $saleError] = self::checkPrice((string) ($row['sale_price'] ?? ''), 'sale price');
        if ($saleError === null && $sale === 0) {
            $saleError = 'The sale price must be more than €0.00.';
        }
        if ($saleError === null && $purchaseError === null && $sale < $purchase) {
            $saleError = 'The sale price can\'t be lower than the purchase price (' . format_price(self::centsToDecimal($purchase)) . ').';
        }
        if ($saleError !== null) {
            $errors['sale_price'] = $saleError;
        }
        $item['sale_cents'] = $sale;

        $shelf = strtoupper((string) ($row['shelf_code'] ?? ''));
        if ($shelf === '') {
            $errors['shelf_code'] = 'Please enter a shelf code.';
        } elseif (!preg_match('/^[A-Z]-\d{2}$/', $shelf) || $shelf[2] . $shelf[3] === '00') {
            $errors['shelf_code'] = 'Shelf codes look like A-01: a letter, a dash and two digits.';
        }
        $item['shelf_code'] = $shelf;

        $photoId = $row['photo_id'] ?? null;
        if (!is_int($photoId)) {
            $errors['photo'] = $row['photo_error'] ?? 'Please add a photo.';
        }
        $item['photo_id'] = is_int($photoId) ? $photoId : null;

        return [$item, $errors];
    }

    /** @return array{0: int, 1: ?string} cents and error message */
    private static function checkPrice(string $input, string $label): array
    {
        if ($input === '') {
            return [0, "Please enter the $label."];
        }
        if (str_starts_with($input, '-')) {
            return [0, 'The ' . $label . ' can\'t be negative.'];
        }
        $cents = self::parsePrice($input);
        if ($cents === null) {
            return [0, 'Enter the ' . $label . ' as an amount in euros, like 12.50.'];
        }
        if ($cents > self::MAX_PRICE_CENTS) {
            return [0, 'The ' . $label . ' is too high.'];
        }
        return [$cents, null];
    }

    // ------------------------------------------------------------------
    // Saving
    // ------------------------------------------------------------------

    /**
     * Save the buy-in and all its items in one transaction (status in_archive).
     *
     * @param array $data validated data from validate()
     * @return int the new buy-in id
     * @throws ShelfCodeTakenException if a shelf code was taken in the meantime (nothing is saved)
     */
    public function save(array $data, int $staffId): int
    {
        $payout = self::payout(array_column($data['items'], 'purchase_cents'), $data['payout_type']);
        $codes = array_column($data['items'], 'shelf_code');

        // Uses a savepoint when the caller already has a transaction open (the tests do).
        $nested = $this->pdo->inTransaction();
        $nested ? $this->pdo->exec('SAVEPOINT intake_save') : $this->pdo->beginTransaction();

        try {
            $taken = array_values(array_intersect($codes, $this->takenShelfCodes()));
            if ($taken !== []) {
                throw new ShelfCodeTakenException($taken);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount)
                 VALUES (:staff, :type, :cash, :payout) RETURNING id'
            );
            $stmt->execute([
                'staff'  => $staffId,
                'type'   => $data['payout_type'],
                'cash'   => self::centsToDecimal($payout['cash']),
                'payout' => self::centsToDecimal($payout['payout']),
            ]);
            $buyInId = (int) $stmt->fetchColumn();

            $stmt = $this->pdo->prepare(
                "INSERT INTO items (buy_in_id, category_id, title, size, colour, condition,
                                    purchase_price, sale_price, photo_id, shelf_code, status)
                 VALUES (:buy_in, :category, :title, :size, :colour, :condition,
                         :purchase, :sale, :photo, :shelf, 'in_archive')"
            );
            foreach ($data['items'] as $item) {
                $stmt->execute([
                    'buy_in'    => $buyInId,
                    'category'  => $item['category_id'],
                    'title'     => $item['title'],
                    'size'      => $item['size'],
                    'colour'    => $item['colour'],
                    'condition' => $item['condition'],
                    'purchase'  => self::centsToDecimal($item['purchase_cents']),
                    'sale'      => self::centsToDecimal($item['sale_cents']),
                    'photo'     => $item['photo_id'],
                    'shelf'     => $item['shelf_code'],
                ]);
            }

            $nested ? $this->pdo->exec('RELEASE SAVEPOINT intake_save') : $this->pdo->commit();
            return $buyInId;
        } catch (Throwable $e) {
            $nested ? $this->pdo->exec('ROLLBACK TO SAVEPOINT intake_save') : $this->pdo->rollBack();

            // Two screens saving the same spot at the same moment: the unique index refuses the second.
            if ($e instanceof PDOException && $e->getCode() === '23505' && str_contains($e->getMessage(), 'items_shelf_code_unsold')) {
                throw new ShelfCodeTakenException(array_values(array_intersect($codes, $this->takenShelfCodes())));
            }
            throw $e;
        }
    }

    /**
     * A saved buy-in for the confirmation screen.
     *
     * @return array{id: int, payout_type: string, cash_amount: string, payout_amount: string, items: list<array>}|null
     */
    public function summary(int $buyInId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, payout_type, cash_amount, payout_amount FROM buy_ins WHERE id = :id');
        $stmt->execute(['id' => $buyInId]);
        $buyIn = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($buyIn === false) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT title, size, shelf_code, sale_price, photo_id, photo_path FROM items WHERE buy_in_id = :id ORDER BY id'
        );
        $stmt->execute(['id' => $buyInId]);
        $items = array_map(static fn (array $r): array => [
            'title'      => $r['title'],
            'size'       => $r['size'],
            'shelf_code' => $r['shelf_code'],
            'sale_price' => $r['sale_price'],
            'photo_url'  => Items::photoUrl($r['photo_id'], $r['photo_path']),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));

        return ['id' => (int) $buyIn['id']] + $buyIn + ['items' => $items];
    }
}
