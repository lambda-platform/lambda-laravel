<?php

namespace Lambda\Dataform;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Intervention\Image\Facades\Image;

/**
 * Upload rules shared by the dataform file fields (FileManager trait) and the
 * editor file manager: size limits, safe file names, SVG checks, image size
 * limits and the img_compress setting from config/lambda.php.
 */
class UploadGuard
{
    // Re-encoded (and scaled down) when img_compress is on. GIF is kept as is
    // so animations survive; BMP is rare enough to keep untouched.
    const COMPRESSIBLE = ['jpg', 'jpeg', 'png', 'webp'];

    // Formats that get a thumbnail.
    const RASTER = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    // Default max size per type group in KB; override with lambda.upload.max_size.<group>.
    const DEFAULT_MAX_KB = [
        'images' => 20480,
        'documents' => 51200,
        'audios' => 51200,
        'videos' => 512000,
        'archives' => 102400,
        'media' => 2048,
    ];

    public static function maxKb(string $group): int
    {
        $configured = (int)Config::get('lambda.upload.max_size.' . $group);
        return $configured > 0 ? $configured : (self::DEFAULT_MAX_KB[$group] ?? 20480);
    }

    public static function tooLarge(UploadedFile $file, string $group): bool
    {
        return $file->getSize() > self::maxKb($group) * 1024;
    }

    /**
     * "My photo (1).php.JPG" -> "My-photo-1-php.jpg". Keeps letters (any script),
     * digits, "-" and "_", so the name is safe in URLs and HTML and can never
     * carry a second extension. The extension must come from an allow-list.
     */
    public static function safeName(string $originalName, string $ext): string
    {
        $dot = strrpos($originalName, '.');
        $base = $dot === false ? $originalName : substr($originalName, 0, $dot);
        $base = (string)preg_replace('/[^\p{L}\p{N}_-]+/u', '-', $base);
        $base = trim(mb_substr($base, 0, 120), '-_');
        return ($base === '' ? 'file' : $base) . '.' . strtolower($ext);
    }

    // `$name`, or "1-$name", "2-$name", ... until `$exists` says it is free.
    public static function uniqueName(string $name, callable $exists): string
    {
        $candidate = $name;
        $i = 0;
        while ($exists($candidate)) {
            $candidate = ++$i . '-' . $name;
        }
        return $candidate;
    }

    /**
     * Pixel limit before decoding, so a small file that unpacks into a huge
     * bitmap (decompression bomb) can't exhaust memory.
     */
    public static function imageWithinLimits(string $path): bool
    {
        $size = @getimagesize($path);
        if (!$size) {
            return true; // not a format getimagesize reads; decoding fails on its own
        }
        $max = (int)Config::get('lambda.upload.max_pixels', 40000000);
        return $size[0] * $size[1] <= $max;
    }

    /**
     * SVG can carry scripts and event handlers that run when the file is opened
     * directly. Reject anything active; plain drawings and icons pass.
     */
    public static function isSafeSvg(string $path): bool
    {
        $svg = @file_get_contents($path);
        if ($svg === false) {
            return false;
        }
        $svg = html_entity_decode($svg, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/<\s*(script|foreignobject|iframe|embed|object|handler)\b|<!entity|\bon[a-z]+\s*=/i', $svg)) {
            return false;
        }
        // Browsers drop tabs and line breaks inside URLs, so "jav&#9;ascript:" still runs.
        $flat = preg_replace('/[\t\n\r]+/', '', $svg);
        return !preg_match('/(java|vb)script\s*:|data\s*:\s*text\/html/i', $flat);
    }

    public static function compressEnabled(): bool
    {
        return (bool)Config::get('lambda.img_compress', true);
    }

    public static function quality(): int
    {
        return max(1, min(100, (int)Config::get('lambda.img_quality', 85)));
    }

    /**
     * The image to store instead of the original, or null to keep the original
     * file byte for byte (img_compress off, or a format that isn't compressed).
     * Compressing turns photos upright, scales them down to img_width and
     * re-encodes at img_quality, which also drops EXIF data (e.g. GPS location).
     */
    public static function compressed(string $path, string $ext)
    {
        if (!self::compressEnabled() || !in_array($ext, self::COMPRESSIBLE, true)) {
            return null;
        }
        $image = self::orientated(Image::make($path), $ext);
        $maxWidth = (int)Config::get('lambda.img_width', 1600);
        if ($maxWidth > 0 && $image->width() > $maxWidth) {
            $image->resize($maxWidth, null, function ($constraint) {
                $constraint->aspectRatio();
            });
        }
        return $image;
    }

    // Thumbnail (img_thumb_width wide) for a raster image, or null for other formats.
    public static function thumbnail(string $path, string $ext)
    {
        if (!in_array($ext, self::RASTER, true)) {
            return null;
        }
        return self::orientated(Image::make($path), $ext)
            ->resize((int)Config::get('lambda.img_thumb_width', 350), null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
    }

    private static function orientated($image, string $ext)
    {
        if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
            try {
                $image->orientate();
            } catch (\Throwable $e) {
                // unreadable EXIF: keep the image as it is
            }
        }
        return $image;
    }
}
