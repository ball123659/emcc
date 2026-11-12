<?php

namespace App\Services\Images;

use App\Services\VirusScan\NullScanner;
use App\Services\VirusScan\ScanResult;
use App\Services\VirusScan\VirusScanner;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileInfo;

/**
 * รับรูปที่อัปโหลด -> ตรวจขนาด/ชนิดจากเนื้อไฟล์ -> สแกนไวรัส -> ย่อด้านยาวไม่เกินกำหนด (คงสัดส่วน)
 * -> แปลงเป็น WebP (คุณภาพ ~85) ด้วย GD -> ตั้งชื่อสุ่ม เก็บนอก public (disk local)
 * การเข้ารหัสใหม่ทำให้ EXIF / GPS / ข้อมูลแฝงในไฟล์ต้นฉบับหายไปทั้งหมด
 */
class ImageProcessor
{
    public const DISK = 'local';

    public function __construct(private readonly VirusScanner $scanner) {}

    public function store(SplFileInfo $file, string $directory, bool $withThumbnail = false, ?int $maxDimension = null): StoredImage
    {
        $path = $file->getRealPath();
        $config = config('emcc.images');

        if ($path === false || ! is_file($path)) {
            throw new ImageRejectedException('อ่านไฟล์ที่อัปโหลดไม่ได้ กรุณาอัปโหลดใหม่อีกครั้ง');
        }

        if (filesize($path) > $config['max_upload_kb'] * 1024) {
            throw new ImageRejectedException('ไฟล์รูปใหญ่เกิน '.($config['max_upload_kb'] / 1024).' MB กรุณาลดขนาดรูปก่อนอัปโหลด');
        }

        // ตรวจชนิดไฟล์จากเนื้อไฟล์จริง ไม่เชื่อนามสกุล
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';

        if (! in_array($mime, $config['accepted_mimes'], true)) {
            throw new ImageRejectedException('รองรับเฉพาะรูป JPG, PNG หรือ WebP เท่านั้น');
        }

        $info = @getimagesize($path);

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new ImageRejectedException('ไฟล์รูปเสียหรือไม่ใช่รูปภาพจริง กรุณาเลือกไฟล์อื่น');
        }

        if ($config['max_source_pixels'] < $info[0] * $info[1]) {
            throw new ImageRejectedException('รูปมีความละเอียดสูงเกินไป กรุณาย่อรูปให้ด้านยาวไม่เกิน 6000 px');
        }

        $this->ensureResources($info[0], $info[1]);

        if ($this->scanner instanceof NullScanner && NullScanner::blocksUploads()) {
            throw new ImageRejectedException('ระบบยังไม่ได้ตั้งค่าโปรแกรมสแกนไวรัส จึงยังรับไฟล์ไม่ได้ กรุณาแจ้งผู้ดูแลระบบ (ตั้ง VIRUS_SCANNER ใน .env)');
        }

        $scan = $this->scanner->scan($path);

        if ($scan === ScanResult::Infected) {
            throw new ImageRejectedException('ตรวจพบไวรัสหรือมัลแวร์ในไฟล์ ระบบไม่รับไฟล์นี้');
        }

        if ($scan !== ScanResult::Clean) {
            throw new ImageRejectedException('สแกนไวรัสไม่สำเร็จ กรุณาลองใหม่อีกครั้ง หรือแจ้งผู้ดูแลระบบ');
        }

        $source = $this->load($path, $mime);
        $source = $this->applyExifOrientation($source, $path, $mime);

        $main = $this->resize($source, $maxDimension ?? $config['max_dimension']);
        $name = Str::uuid()->toString();
        $directory = trim($directory, '/');
        $mainPath = "{$directory}/{$name}.webp";
        $size = $this->put($mainPath, $main, $config['webp_quality']);
        $width = imagesx($main);
        $height = imagesy($main);

        $thumbPath = null;

        if ($withThumbnail) {
            $thumb = $this->resize($source, $config['thumb_dimension']);
            $thumbPath = "{$directory}/{$name}_thumb.webp";
            $this->put($thumbPath, $thumb, $config['webp_quality']);
        }

        return new StoredImage($mainPath, $thumbPath, $width, $height, $size);
    }

    /**
     * เตรียมหน่วยความจำ/เวลาให้พอสำหรับรูปขนาดใหญ่
     * (PHP บน Windows นับหน่วยความจำของ GD เข้า memory_limit — รูปกล้อง 24 ล้านพิกเซลใช้ ~100 MB ต่อสำเนา)
     */
    private function ensureResources(int $width, int $height): void
    {
        @set_time_limit(120);

        $needed = (int) ($width * $height * 5 * 2.2) + 64 * 1024 * 1024;
        $current = self::iniBytes((string) ini_get('memory_limit'));

        if ($current !== -1 && $current < $needed) {
            $cap = 1024 * 1024 * 1024;

            if ($needed > $cap || @ini_set('memory_limit', (string) $needed) === false) {
                throw new ImageRejectedException('รูปมีความละเอียดสูงเกินกว่าที่เซิร์ฟเวอร์ประมวลผลได้ กรุณาย่อรูปให้ด้านยาวไม่เกิน 4000 px แล้วอัปโหลดใหม่');
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
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function load(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new ImageRejectedException('เปิดไฟล์รูปไม่ได้ (ไฟล์อาจเสีย) กรุณาบันทึกรูปใหม่แล้วอัปโหลดอีกครั้ง');
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

        // 2,4,5,7 = ภาพกลับด้าน (กล้องหน้า/มือถือบางรุ่น)
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6, 5 => imagerotate($image, -90, 0),
            8, 7 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function resize(GdImage $source, int $maxDimension): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxDimension / max($width, $height));

        if ($scale >= 1) {
            return $source;
        }

        $newWidth = max(1, (int) round($width * $scale));
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
            throw new ImageRejectedException('แปลงรูปไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
        }

        Storage::disk(self::DISK)->put($path, $binary);

        return strlen($binary);
    }

    /** ลบไฟล์รูป (และ thumbnail ถ้ามี) */
    public static function delete(?string ...$paths): void
    {
        $paths = array_values(array_filter($paths));

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}
