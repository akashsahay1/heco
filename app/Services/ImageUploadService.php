<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Stores uploaded (or remotely-downloaded) images under
 * public/uploads/<type>/YYYY/MM/<uuid>.<ext>, resizing them with PHP GD so we
 * never persist huge originals. If GD is unavailable the original bytes are
 * saved verbatim and a notice is logged.
 *
 * Used for traveller profile photos (and OAuth avatar mirroring) — extend the
 * $type argument for experiences/trips/providers etc. as needed.
 */
class ImageUploadService
{
    /** Allowed extensions (lower-case). */
    public const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Store an uploaded image. Returns the public-relative path
     * (e.g. "/uploads/users/2026/05/abc123.jpg") or null on failure.
     */
    public static function storeUploadedImage(UploadedFile $file, string $type, int $maxDimension = 512): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return null;
        }

        [$dir, $relativeDir] = self::ensureDir($type);
        $filename = (string) Str::uuid() . '.' . $ext;
        $absolutePath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!self::resizeWithGd($file->getRealPath(), $absolutePath, $ext, $maxDimension)) {
            // GD missing or failed — keep the original so the feature still works.
            try {
                $file->move($dir, $filename);
            } catch (\Throwable $e) {
                Log::error('ImageUploadService: failed to store uploaded image: ' . $e->getMessage());
                return null;
            }
            // NOTE: image resize was skipped (GD unavailable) — original kept as-is.
        }

        return '/' . $relativeDir . '/' . $filename;
    }

    /**
     * Download a remote image (e.g. an OAuth avatar URL) and store it locally.
     * Returns the public-relative path or null on failure.
     */
    public static function storeRemoteImage(string $url, string $type, int $maxDimension = 512): ?string
    {
        try {
            $context = stream_context_create([
                'http'  => ['timeout' => 8, 'follow_location' => 1, 'user_agent' => 'HECO/1.0'],
                'https' => ['timeout' => 8],
            ]);
            $bytes = @file_get_contents($url, false, $context);
            if ($bytes === false || strlen($bytes) === 0) {
                return null;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'heco_img_');
            file_put_contents($tmp, $bytes);

            $info = @getimagesize($tmp);
            if ($info === false) {
                @unlink($tmp);
                return null;
            }
            $ext = match ($info[2]) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_WEBP => 'webp',
                IMAGETYPE_GIF  => 'png',
                default        => null,
            };
            if ($ext === null) {
                @unlink($tmp);
                return null;
            }

            [$dir, $relativeDir] = self::ensureDir($type);
            $filename = (string) Str::uuid() . '.' . $ext;
            $absolutePath = $dir . DIRECTORY_SEPARATOR . $filename;

            if (!self::resizeWithGd($tmp, $absolutePath, $ext, $maxDimension)) {
                if (!@copy($tmp, $absolutePath)) {
                    @unlink($tmp);
                    return null;
                }
                // NOTE: image resize was skipped (GD unavailable) — original kept as-is.
            }
            @unlink($tmp);

            return '/' . $relativeDir . '/' . $filename;
        } catch (\Throwable $e) {
            Log::warning('ImageUploadService: remote image download failed (' . $url . '): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a previously stored local upload. Safely ignores remote URLs
     * (http/https) and paths outside the uploads directory.
     */
    public static function deleteLocal(?string $relativePath): void
    {
        if (empty($relativePath) || Str::startsWith($relativePath, ['http://', 'https://'])) {
            return;
        }
        $clean = ltrim($relativePath, '/');
        if (!Str::startsWith($clean, 'uploads/')) {
            return;
        }
        $abs = public_path($clean);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    /**
     * The uploads layout, in one place: public/uploads/<type>/YYYY/MM.
     *
     * Public because it is the convention and not this class's private
     * business — a provider's verification documents sit in the same folder as
     * their photo, but are stored verbatim rather than resized, so they are
     * written by their own caller.
     *
     * @return array{0:string,1:string} [absolute dir, public-relative dir]
     */
    public static function ensureDir(string $type): array
    {
        $relativeDir = 'uploads/' . trim($type, '/') . '/' . date('Y') . '/' . date('m');
        $dir = public_path($relativeDir);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return [$dir, $relativeDir];
    }

    /**
     * Turn a photo the right way up before it is resized.
     *
     * A phone camera writes the image the way the sensor read it and records
     * how to turn it in an EXIF tag; the browser applies that tag, which is
     * why the original looks fine. GD neither reads the tag nor writes one,
     * so the resized copy came out lying on its side. Rotating here bakes the
     * correction into the pixels, where nothing can lose it again.
     *
     * Only JPEG carries the tag, and only the JPEGs a camera produced carry a
     * useful one — everything else is returned untouched.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private static function applyExifOrientation($image, string $srcPath, int $type)
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data') || !function_exists('imagerotate')) {
            return $image;
        }

        $exif = @exif_read_data($srcPath);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        // 2, 4, 5 and 7 are mirrored as well as turned — a front-camera shot
        // can be. The mirror goes first, then the rotation, which is the order
        // the tag's values are defined in.
        if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        // imagerotate turns counter-clockwise, so a clockwise value is negative.
        $angle = match ($orientation) {
            3, 4 => 180,
            6, 7 => -90,
            5, 8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }

    /**
     * Resize $srcPath into $destPath (square-bounded by $maxDimension, aspect
     * preserved). Returns false if GD is unavailable or the source is unreadable.
     */
    private static function resizeWithGd(string $srcPath, string $destPath, string $ext, int $maxDimension): bool
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) {
            return false;
        }

        $info = @getimagesize($srcPath);
        if ($info === false) {
            return false;
        }

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($srcPath) : false,
            IMAGETYPE_PNG  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($srcPath) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false,
            IMAGETYPE_GIF  => function_exists('imagecreatefromgif') ? @imagecreatefromgif($srcPath) : false,
            default        => false,
        };
        if (!$src) {
            return false;
        }

        // Before the dimensions are read: turning a photo upright swaps them.
        $src = self::applyExifOrientation($src, $srcPath, $info[2]);

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxDimension / max($w, $h));
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($newW, $newH);
        if (in_array($ext, ['png', 'webp'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $ok = match ($ext) {
            'jpg', 'jpeg' => function_exists('imagejpeg') ? imagejpeg($dst, $destPath, 85) : false,
            'png'         => function_exists('imagepng') ? imagepng($dst, $destPath, 6) : false,
            'webp'        => function_exists('imagewebp') ? imagewebp($dst, $destPath, 85) : false,
            default       => false,
        };

        imagedestroy($src);
        imagedestroy($dst);

        return (bool) $ok;
    }
}
