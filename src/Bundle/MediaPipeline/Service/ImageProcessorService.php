<?php

namespace App\Bundle\MediaPipeline\Service;

/**
 * ImageProcessorService
 *
 * Resizes and converts uploaded images to WebP using PHP's native GD extension.
 * Generates three responsive widths: 320, 640, and 1280 pixels.
 *
 * Requirements: GD extension (standard in PHP 8.x).
 *
 * Returns:
 *   [
 *     'webp'   => string  // absolute path of the 1280w WebP master
 *     'srcset' => [       // keyed by width
 *       '320w'  => string (relative web path),
 *       '640w'  => string,
 *       '1280w' => string,
 *     ]
 *   ]
 */
class ImageProcessorService
{
    /** Widths to generate in pixels */
    private const SIZES = [320, 640, 1280];

    /** WebP quality 0-100 */
    private const QUALITY = 82;

    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Process an uploaded file and generate WebP variants.
     *
     * @param string $absolutePath Absolute filesystem path to the original upload
     * @return array{webp: string, srcset: array<string, string>}|null  null on failure
     */
    public function process(string $absolutePath): ?array
    {
        if (!extension_loaded('gd') || !file_exists($absolutePath)) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: '';
        $src  = $this->loadImage($absolutePath, $mime);
        if (!$src) return null;

        $origW = imagesx($src);
        $origH = imagesy($src);
        $dir   = dirname($absolutePath);
        $base  = pathinfo($absolutePath, PATHINFO_FILENAME);

        // Relative web root for URL generation
        $publicDir = $this->projectDir . '/public';

        $srcset = [];
        $masterWebP = null;

        foreach (self::SIZES as $targetW) {
            if ($targetW > $origW) {
                // Don't upscale — use original dimensions for small images
                $targetW = $origW;
            }

            $targetH = (int) round(($targetW / $origW) * $origH);
            $dst = imagecreatetruecolor($targetW, $targetH);

            // Preserve transparency for PNG
            if (in_array($mime, ['image/png', 'image/gif'], true)) {
                imagepalettetotruecolor($dst);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent);
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

            $outPath = $dir . '/' . $base . '_' . $targetW . 'w.webp';
            imagewebp($dst, $outPath, self::QUALITY);
            imagedestroy($dst);

            // Web-relative path (strip public dir prefix)
            $relPath = '/' . ltrim(str_replace('\\', '/', substr($outPath, strlen($publicDir))), '/');
            $srcset["{$targetW}w"] = $relPath;

            if ($targetW === max(self::SIZES) || ($masterWebP === null)) {
                $masterWebP = $outPath;
            }
        }

        imagedestroy($src);

        return ['webp' => $masterWebP, 'srcset' => $srcset];
    }

    private function loadImage(string $path, string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => false,
        };
    }
}
