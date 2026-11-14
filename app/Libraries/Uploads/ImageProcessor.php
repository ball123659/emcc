<?php

namespace App\Libraries\Uploads;

use App\Libraries\Storage;
use App\Libraries\VirusScan\NullScanner;
use App\Libraries\VirusScan\ScanResult;
use App\Libraries\VirusScan\VirusScanner;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Emcc;
use GdImage;

/**
 * รับรูปที่อัปโหลด -> ตรวจขนาด/ชนิดจากเนื้อไฟล์ -> สแกนไวรัส -> ย่อด้านยาวไม่เกินกำหนด (คงสัดส่วน)
 * -> แปลงเป็น WebP (คุณภาพ ~85) ด้วย GD -> ตั้งชื่อสุ่ม เก็บนอก public
 * การเข้ารหัสใหม่ทำให้ EXIF / GPS / ข้อมูลแฝงในไฟล์ต้นฉบับหายไปทั้งหมด
 */
class ImageProcessor
{
    public const ACCEPTED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly VirusScanner $scanner)
    {
    }

    /** รับไฟล์จากฟอร์ม (ตรวจว่าอัปโหลดสำเร็จก่อน) */
    public function storeUpload(UploadedFile $file, string $directory, bool $withThumbnail = false, ?int $maxDimension = null): StoredImage
    {
        UploadCheck::assertUploaded($file, config(Emcc::class)->imageMaxKb);

        return $this->store($file->getTempName(), $directory, $withThumbnail, $maxDimension);
    }

    public function store(string $path, string $directory, bool $withThumbnail = false, ?int $maxDimension = null): StoredImage
    {
        $config = config(Emcc::class);

        if (! is_file($path)) {
            throw new UploadRejected('อ่านไฟล์ที่อัปโหลดไม่ได้ กรุณาอัปโหลดใหม่อีกครั้ง');
        }

        if (filesize($path) > $config->imageMaxKb * 1024) {
            throw new UploadRejected('ไฟล์รูปใหญ่เกิน ' . ($config->imageMaxKb / 1024) . ' MB กรุณาลดขนาดรูปก่อนอัปโหลด');
        }

        // ตรวจชนิดไฟล์จากเนื้อไฟล์จริง ไม่เชื่อนามสกุล
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';

        if (! in_array($mime, self::ACCEPTED_MIMES, true)) {
            throw new UploadRejected('รองรับเฉพาะรูป JPG, PNG หรือ WebP เท่านั้น');
        }

        $info = @getimagesize($path);

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new UploadRejected('ไฟล์รูปเสียหรือไม่ใช่รูปภาพจริง กรุณาเลือกไฟล์อื่น');
        }

        if ($config->imageMaxSourcePixels < $info[0] * $info[1]) {
            throw new UploadRejected('รูปมีความละเอียดสูงเกินไป กรุณาย่อรูปให้ด้านยาวไม่เกิน 6000 px');
        }

        $this->ensureResources($info[0], $info[1]);
        $this->scanOrFail($path);

        $source = $this->load($path, $mime);
        $source = $this->applyExifOrientation($source, $path, $mime);
        $main   = $this->resize($source, $maxDimension ?? $config->imageMaxDimension);

        $name      = Storage::uuid();
        $directory = trim($directory, '/');
        $mainPath  = "{$directory}/{$name}.webp";
        $size      = $this->put($mainPath, $main, $config->webpQuality);

        $thumbPath = null;

        if ($withThumbnail) {
            $thumbPath = "{$directory}/{$name}_thumb.webp";
            $this->put($thumbPath, $this->resize($source, $config->thumbDimension), $config->webpQuality);
        }

        return new StoredImage($mainPath, $thumbPath, imagesx($main), imagesy($main), $size);
    }

    private function scanOrFail(string $path): void
    {
        if ($this->scanner instanceof NullScanner && NullScanner::blocksUploads()) {
            throw new UploadRejected('ระบบยังไม่ได้ตั้งค่าโปรแกรมสแกนไวรัส จึงยังรับไฟล์ไม่ได้ กรุณาแจ้งผู้ดูแลระบบ (ตั้ง emcc.virusScanner ใน .env)');
        }

        $scan = $this->scanner->scan($path);

        if ($scan === ScanResult::Infected) {
            throw new UploadRejected('ตรวจพบไวรัสหรือมัลแวร์ในไฟล์ ระบบไม่รับไฟล์นี้');
        }

        if ($scan !== ScanResult::Clean) {
            throw new UploadRejected('สแกนไวรัสไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หรือแจ้งผู้ดูแลระบบ');
        }
    }

    /**
     * เตรียมหน่วยความจำ/เวลาให้พอสำหรับรูปขนาดใหญ่
     * (PHP บน Windows นับหน่วยความจำของ GD เข้า memory_limit — รูปกล้อง 24 ล้านพิกเซลใช้ ~100 MB ต่อสำเนา)
     */
    private function ensureResources(int $width, int $height): void
    {
        @set_time_limit(120);

        $needed  = (int) ($width * $height * 5 * 2.2) + 64 * 1024 * 1024;
        $current = self::iniBytes((string) ini_get('memory_limit'));

        if ($current !== -1 && $current < $needed) {
            if ($needed > 1024 * 1024 * 1024 || @ini_set('memory_limit', (string) $needed) === false) {
                throw new UploadRejected('รูปมีความละเอียดสูงเกินกว่าที่เซิร์ฟเวอร์ประมวลผลได้ กรุณาย่อรูปให้ด้านยาวไม่เกิน 4000 px แล้วอัปโหลดใหม่');
            }
        }
    }

    public static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g'     => $number * 1024 ** 3,
            'm'     => $number * 1024 ** 2,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    private function load(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => false,
        };

        if (! $image instanceof GdImage) {
            throw new UploadRejected('เปิดไฟล์รูปไม่ได้ (ไฟล์อาจเสีย) กรุณาบันทึกรูปใหม่แล้วอัปโหลดอีกครั้ง');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /** หมุนรูปตาม EXIF Orientation ของกล้อง/มือถือ ก่อนที่ EXIF จะถูกทิ้ง */
    private function applyExifOrientation(GdImage $image, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        $rotated = match ($orientation) {
            3       => imagerotate($image, 180, 0),
            6, 5    => imagerotate($image, -90, 0),
            8, 7    => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function resize(GdImage $source, int $maxDimension): GdImage
    {
        $width  = imagesx($source);
        $height = imagesy($source);
        $scale  = min(1, $maxDimension / max($width, $height));

        if ($scale >= 1) {
            return $source;
        }

        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $target;
    }

    private function put(string $path, GdImage $image, int $quality): int
    {
        ob_start();
        imagewebp($image, null, $quality);
        $binary = (string) ob_get_clean();

        if ($binary === '') {
            throw new UploadRejected('แปลงรูปไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
        }

        return Storage::put($path, $binary);
    }

    /** path ของรูปย่อที่สร้างคู่กับรูปหลัก */
    public static function thumbnailPathFor(string $path): string
    {
        return preg_replace('/\.webp$/', '_thumb.webp', $path);
    }
}
