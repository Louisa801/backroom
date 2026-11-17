<?php
declare(strict_types=1);

/**
 * Item photos: check an upload, resize it to a JPEG, store it in the database.
 * Uploaded photos are kept in the photos table because Render's free tier loses files on restart.
 */
final class Photos
{
    public const MAX_BYTES = 8 * 1024 * 1024;   // 8 MB, same as upload_max_filesize
    public const MAX_WIDTH = 1200;
    public const JPEG_QUALITY = 80;
    public const MAX_PIXELS = 40_000_000;       // protects memory while decoding
    public const PENDING_HOURS = 24;

    /** Accepted types, detected from the file content (never from the name). */
    private const TYPES = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png'  => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    private const NOT_A_PHOTO = 'This file is not a photo. Please choose a JPEG, PNG or WebP image.';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Plain-language message for a failed upload, or null if the upload worked.
     * UPLOAD_ERR_NO_FILE gives null too: "no photo chosen" is decided by the caller.
     */
    public static function uploadError(array $file): ?string
    {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        return match ($code) {
            UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE           => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE   => 'This photo is larger than 8 MB. Please choose a smaller photo.',
            UPLOAD_ERR_PARTIAL                          => 'The photo upload was interrupted. Please try again.',
            default                                     => 'The photo could not be uploaded. Please try again.',
        };
    }

    /**
     * Check that the file really is a JPEG, PNG or WebP image and turn it into
     * a JPEG of at most MAX_WIDTH pixels wide, the right way up, on a white background.
     *
     * @return array{data: string, width: int, height: int}
     * @throws InvalidArgumentException with a message that can be shown to the user
     */
    public static function process(string $path): array
    {
        $bytes = is_file($path) ? filesize($path) : false;
        if ($bytes === false || $bytes === 0) {
            throw new InvalidArgumentException(self::NOT_A_PHOTO);
        }
        if ($bytes > self::MAX_BYTES) {
            throw new InvalidArgumentException('This photo is larger than 8 MB. Please choose a smaller photo.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $info = @getimagesize($path);
        if (!isset(self::TYPES[$mime]) || $info === false || $info[2] !== self::TYPES[$mime]) {
            throw new InvalidArgumentException(self::NOT_A_PHOTO);
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('This photo has too many pixels (over 40 megapixels). Please choose a smaller photo.');
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            throw new InvalidArgumentException(self::NOT_A_PHOTO);
        }

        if ($mime === 'image/jpeg') {
            $image = self::applyExifOrientation($image, $path);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $newWidth = min($width, self::MAX_WIDTH);
        $newHeight = max(1, (int) round($height * $newWidth / $width));

        // Copy onto white so transparent PNG/WebP areas don't turn black in the JPEG.
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imageinterlace($canvas, true);

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);
        $data = (string) ob_get_clean();

        return ['data' => $data, 'width' => $newWidth, 'height' => $newHeight];
    }

    /**
     * The photo for one item row of a form: a new upload (checked, resized, stored as pending)
     * or a photo kept from an earlier attempt of the same form.
     *
     * @param array|null $file the row's $_FILES entry
     * @param string $pendingRef id of a kept photo, sent back by the form
     * @param list<int> $sessionPending ids this session uploaded; a kept photo must be one of them
     * @return array{0: ?int, 1: ?string} photo id and error message; [null, null] means no photo chosen
     */
    public function fromForm(?array $file, string $pendingRef, array &$sessionPending, bool $mustBeUploaded = true): array
    {
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $error = self::uploadError($file);
            if ($error !== null) {
                return [null, $error];
            }
            if ($mustBeUploaded && !is_uploaded_file($file['tmp_name'])) {
                return [null, 'The photo could not be uploaded. Please try again.'];
            }
            try {
                $id = $this->store(self::process($file['tmp_name']));
            } catch (InvalidArgumentException $e) {
                return [null, $e->getMessage()];
            }
            $sessionPending[] = $id;
            return [$id, null];
        }

        if (ctype_digit($pendingRef) && in_array((int) $pendingRef, $sessionPending, true) && $this->isPending((int) $pendingRef)) {
            return [(int) $pendingRef, null];
        }
        return [null, null];
    }

    /** Save a processed photo. It stays "pending" until an item points to it. */
    public function store(array $photo): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO photos (mime_type, data, width, height) VALUES ('image/jpeg', :data, :width, :height) RETURNING id"
        );
        $stmt->bindValue('data', $photo['data'], PDO::PARAM_LOB);
        $stmt->bindValue('width', $photo['width'], PDO::PARAM_INT);
        $stmt->bindValue('height', $photo['height'], PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** @return array{data: string, mime_type: string}|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT mime_type, data FROM photos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $data = is_resource($row['data']) ? stream_get_contents($row['data']) : $row['data'];
        return ['mime_type' => $row['mime_type'], 'data' => (string) $data];
    }

    /** True if the photo exists and no item uses it yet. */
    public function isPending(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM photos p WHERE p.id = :id AND NOT EXISTS (SELECT 1 FROM items i WHERE i.photo_id = p.id)'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() !== false;
    }

    /** Delete pending photos older than PENDING_HOURS (abandoned buy-in forms). */
    public function deleteStalePending(): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM photos p
             WHERE p.created_at < now() - make_interval(hours => :hours)
               AND NOT EXISTS (SELECT 1 FROM items i WHERE i.photo_id = p.id)'
        );
        $stmt->execute(['hours' => self::PENDING_HOURS]);
        return $stmt->rowCount();
    }

    /** Phones store photos sideways and record the rotation in EXIF; turn them upright. */
    private static function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        switch ($orientation) {
            case 2: imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 3: $image = imagerotate($image, 180, 0); break;
            case 4: imageflip($image, IMG_FLIP_VERTICAL); break;
            case 5: imageflip($image, IMG_FLIP_VERTICAL); $image = imagerotate($image, -90, 0); break;
            case 6: $image = imagerotate($image, -90, 0); break;
            case 7: imageflip($image, IMG_FLIP_HORIZONTAL); $image = imagerotate($image, -90, 0); break;
            case 8: $image = imagerotate($image, 90, 0); break;
        }
        return $image;
    }
}
