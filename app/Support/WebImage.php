<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Small web copies of the public website's photos. The originals are print-size (a tour flyer is
 * 1800px and 1–2 MB) but show in cards a few hundred pixels wide, so pages ask for a copy at the
 * width they show it: a WebP, cached in thumbs/ of the folder the web server serves and named after
 * the original's path, size and modified time, so replacing a photo makes a fresh copy.
 *
 * A page makes at most a few missing copies while it renders and links the original for the rest,
 * so the first visit after a deploy is never held up; `php artisan images:optimize` makes them all.
 */
class WebImage
{
    /** Widths the site asks for: package and gallery cards, logos, the hero marquee, full-screen previews. */
    public const CARD = 800;

    public const LOGO = 240;

    public const MARQUEE = 640;

    public const WIDE = 1600;

    private const QUALITY = 78;

    /** Copies one request may make before it falls back to originals. */
    private const PER_REQUEST = 6;

    private static int $made = 0;

    /** The URL to show $path (relative to public/, as stored in the database) at $width pixels wide. */
    public static function url(?string $path, int $width = self::CARD): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
            return $path;
        }

        $original = asset($path);
        $source = self::source($path);
        if ($source === null || ! preg_match('/\.(jpe?g|png|webp)$/i', $source)) {
            return $original;
        }

        $name = 'thumbs/'.$width.'/'.md5($path.'|'.filesize($source).'|'.filemtime($source)).'.webp';
        $target = self::webRoot().'/'.$name;

        if (! is_file($target)) {
            if (self::$made >= self::PER_REQUEST && ! app()->runningInConsole()) {
                return $original;
            }
            self::$made++;
            if (! self::make($source, $target, $width)) {
                return $original;
            }
        }

        // The copy turned out no smaller (an already small photo): keep the original.
        return filesize($target) === 0 ? $original : asset($name);
    }

    /**
     * The folder the web server serves. On the cPanel host that is public_html beside the app (the
     * deploy copies public/ into it, and the host refuses to follow links such as /storage), so the
     * copies are written there directly; elsewhere it is public/. WEB_ROOT in .env overrides both.
     */
    public static function webRoot(): string
    {
        if ($root = config('filesystems.web_root')) {
            return rtrim($root, '/\\');
        }

        $cpanel = dirname(base_path(), 2).'/public_html';

        return is_dir($cpanel) ? $cpanel : public_path();
    }

    /** Where the original is on disk: uploads under storage/, everything else under public/. */
    private static function source(string $path): ?string
    {
        $path = ltrim(rawurldecode($path), '/');
        if (str_contains($path, '..')) {
            return null;
        }

        $file = Str::startsWith($path, 'storage/')
            ? storage_path('app/public/'.Str::after($path, 'storage/'))
            : public_path($path);

        return is_file($file) ? $file : null;
    }

    private static function make(string $source, string $target, int $width): bool
    {
        if (! function_exists('imagewebp')) {
            return false;
        }

        try {
            $info = @getimagesize($source);
            if (! $info || $info[0] * $info[1] > 40_000_000) {
                return false;
            }
            [$w, $h] = $info;

            $image = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
                IMAGETYPE_PNG => @imagecreatefrompng($source),
                IMAGETYPE_WEBP => @imagecreatefromwebp($source),
                default => false,
            };
            if (! $image) {
                return false;
            }

            if ($w > $width) {
                $scaled = imagescale($image, $width, (int) round($h * $width / $w), IMG_BICUBIC);
                imagedestroy($image);
                $image = $scaled;
            }
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);

            if (! is_dir(dirname($target))) {
                @mkdir(dirname($target), 0755, true);
                @chmod(dirname($target), 0755);
                @chmod(dirname($target, 2), 0755);
            }
            // Written aside and moved in, so a visitor never gets a half-written file.
            $temp = $target.'.'.Str::random(6).'.tmp';
            $ok = imagewebp($image, $temp, self::QUALITY);
            imagedestroy($image);
            if (! $ok) {
                @unlink($temp);

                return false;
            }

            // No smaller than the original: an empty marker says "use the original".
            if (filesize($temp) >= filesize($source)) {
                file_put_contents($temp, '');
            }

            // Readable by the web server, whichever user PHP runs as.
            @chmod($temp, 0644);

            return rename($temp, $target);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
