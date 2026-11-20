<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Checking, resizing and storing item photos. Test images are made with GD. */
final class PhotosTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    private function tempFile(string $contents = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bkphoto');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;
        return $path;
    }

    private function image(string $type, int $width, int $height, bool $transparent = false): string
    {
        $img = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagesavealpha($img, true);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        } else {
            imagefill($img, 0, 0, imagecolorallocate($img, 169, 71, 42));
        }
        $path = $this->tempFile();
        match ($type) {
            'png'  => imagepng($img, $path),
            'jpeg' => imagejpeg($img, $path, 90),
            'webp' => imagewebp($img, $path),
        };
        return $path;
    }

    private static function assertIsJpeg(string $data): void
    {
        self::assertSame('image/jpeg', (new finfo(FILEINFO_MIME_TYPE))->buffer($data));
    }

    public function testLargePhotoIsResizedTo1200PixelsWide(): void
    {
        $photo = Photos::process($this->image('jpeg', 3000, 2000));

        self::assertSame(1200, $photo['width']);
        self::assertSame(800, $photo['height']);
        self::assertIsJpeg($photo['data']);
        self::assertSame([1200, 800], array_slice(getimagesizefromstring($photo['data']), 0, 2));
    }

    public function testSmallPhotoKeepsItsSize(): void
    {
        $photo = Photos::process($this->image('jpeg', 800, 1000));
        self::assertSame([800, 1000], [$photo['width'], $photo['height']]);
    }

    public function testPngAndWebpBecomeJpeg(): void
    {
        self::assertIsJpeg(Photos::process($this->image('png', 1600, 1600))['data']);
        self::assertIsJpeg(Photos::process($this->image('webp', 500, 400))['data']);
    }

    public function testTransparentAreasBecomeWhite(): void
    {
        $photo = Photos::process($this->image('png', 100, 100, transparent: true));
        $img = imagecreatefromstring($photo['data']);
        $rgb = imagecolorsforindex($img, imagecolorat($img, 50, 50));

        self::assertGreaterThan(245, $rgb['red']);
        self::assertGreaterThan(245, $rgb['green']);
        self::assertGreaterThan(245, $rgb['blue']);
    }

    public function testTextFileRenamedToJpgIsRefused(): void
    {
        $path = $this->tempFile("<?php echo 'not a photo'; ?>");
        rename($path, $path . '.jpg');
        $this->tempFiles[] = $path . '.jpg';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This file is not a photo');
        Photos::process($path . '.jpg');
    }

    public function testFileWithOnlyAnImageHeaderIsRefused(): void
    {
        // Starts like a PNG but is not a real image.
        $this->expectException(InvalidArgumentException::class);
        Photos::process($this->tempFile("\x89PNG\r\n\x1a\n" . str_repeat('x', 100)));
    }

    public function testGifIsRefused(): void
    {
        $img = imagecreatetruecolor(10, 10);
        $path = $this->tempFile();
        imagegif($img, $path);

        $this->expectException(InvalidArgumentException::class);
        Photos::process($path);
    }

    public function testEmptyFileIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Photos::process($this->tempFile(''));
    }

    public function testFileOver8MbIsRefused(): void
    {
        $path = $this->tempFile();
        $handle = fopen($path, 'r+');
        ftruncate($handle, Photos::MAX_BYTES + 1);
        fclose($handle);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('larger than 8 MB');
        Photos::process($path);
    }

    public function testUploadErrorsGivePlainMessages(): void
    {
        self::assertNull(Photos::uploadError(['error' => UPLOAD_ERR_OK]));
        self::assertNull(Photos::uploadError(['error' => UPLOAD_ERR_NO_FILE]));
        self::assertStringContainsString('larger than 8 MB', Photos::uploadError(['error' => UPLOAD_ERR_INI_SIZE]));
        self::assertStringContainsString('interrupted', Photos::uploadError(['error' => UPLOAD_ERR_PARTIAL]));
        self::assertStringContainsString('could not be uploaded', Photos::uploadError(['error' => UPLOAD_ERR_NO_TMP_DIR]));
    }

    // ---------------- database ----------------

    private function pdo(): PDO
    {
        if (getenv('DATABASE_URL') === false || getenv('DATABASE_URL') === '') {
            self::markTestSkipped('DATABASE_URL is not set.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        return $pdo;
    }

    public function testStoreAndFindRoundTrip(): void
    {
        $pdo = $this->pdo();
        try {
            $photos = new Photos($pdo);
            $processed = Photos::process($this->image('png', 1500, 1000));
            $id = $photos->store($processed);

            $found = $photos->find($id);
            self::assertSame('image/jpeg', $found['mime_type']);
            self::assertSame($processed['data'], $found['data'], 'Bytes come back unchanged.');
            self::assertNull($photos->find($id + 1000));
            self::assertTrue($photos->isPending($id));
        } finally {
            $pdo->rollBack();
        }
    }

    public function testFromFormStoresANewUploadAndKeepsIt(): void
    {
        $pdo = $this->pdo();
        try {
            $photos = new Photos($pdo);
            $pending = [];
            $file = ['name' => 'coat.png', 'tmp_name' => $this->image('png', 300, 300), 'size' => 1, 'error' => UPLOAD_ERR_OK];

            [$id, $error] = $photos->fromForm($file, '', $pending, mustBeUploaded: false);
            self::assertIsInt($id);
            self::assertNull($error);
            self::assertSame([$id], $pending);

            // Second attempt of the form: no new file, the kept photo is used.
            self::assertSame([$id, null], $photos->fromForm(null, (string) $id, $pending, mustBeUploaded: false));

            // A photo id this session did not upload is ignored.
            $other = [];
            self::assertSame([null, null], $photos->fromForm(null, (string) $id, $other, mustBeUploaded: false));
        } finally {
            $pdo->rollBack();
        }
    }

    public function testFromFormReportsAFileThatIsNotAPhoto(): void
    {
        $pdo = $this->pdo();
        try {
            $pending = [];
            $file = ['name' => 'x.jpg', 'tmp_name' => $this->tempFile('hello'), 'size' => 5, 'error' => UPLOAD_ERR_OK];
            [$id, $error] = (new Photos($pdo))->fromForm($file, '', $pending, mustBeUploaded: false);

            self::assertNull($id);
            self::assertStringContainsString('not a photo', $error);
            self::assertSame([], $pending);
        } finally {
            $pdo->rollBack();
        }
    }

    public function testFromFormRefusesFilesThatWereNotUploaded(): void
    {
        $pdo = $this->pdo();
        try {
            $pending = [];
            $file = ['name' => 'coat.png', 'tmp_name' => $this->image('png', 10, 10), 'size' => 1, 'error' => UPLOAD_ERR_OK];
            [$id, $error] = (new Photos($pdo))->fromForm($file, '', $pending);

            self::assertNull($id);
            self::assertNotNull($error);
        } finally {
            $pdo->rollBack();
        }
    }

    public function testStalePendingPhotosAreDeletedButUsedOnesAreKept(): void
    {
        $pdo = $this->pdo();
        try {
            $photos = new Photos($pdo);
            $data = Photos::process($this->image('jpeg', 10, 10));
            $old = $photos->store($data);
            $fresh = $photos->store($data);
            $used = $photos->store($data);
            $pdo->exec("UPDATE photos SET created_at = now() - interval '2 days' WHERE id IN ($old, $used)");

            $staffId = test_staff_id($pdo);
            $buyIn = (int) $pdo->query("INSERT INTO buy_ins (staff_id, payout_type, cash_amount, payout_amount) VALUES ($staffId, 'cash', 1, 1) RETURNING id")->fetchColumn();
            $category = (int) $pdo->query('SELECT min(id) FROM categories')->fetchColumn();
            $pdo->exec("INSERT INTO items (buy_in_id, category_id, title, size, colour, condition, purchase_price, sale_price, photo_id, shelf_code)
                        VALUES ($buyIn, $category, 'Photo test', 'M', 'black', 'good', 1, 2, $used, 'Y-50')");

            $photos->deleteStalePending();

            self::assertNull($photos->find($old), 'Old pending photo is deleted.');
            self::assertNotNull($photos->find($fresh), 'Recent pending photo is kept.');
            self::assertNotNull($photos->find($used), 'Photo of an item is kept.');
            self::assertFalse($photos->isPending($used));
        } finally {
            $pdo->rollBack();
        }
    }
}
