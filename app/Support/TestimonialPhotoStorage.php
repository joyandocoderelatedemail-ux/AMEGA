<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Client photos for testimonials, uploaded from the admin. They are shown to anyone on the public site,
 * so they live on the web-served uploads disk (like destination photos), never the private documents disk.
 * Paths handed back by store() are relative to the public directory ("uploads/testimonials/..."), ready
 * for WebImage::url().
 */
class TestimonialPhotoStorage
{
    public const DISK = 'uploads';

    public const DIRECTORY = 'testimonials';

    public const PUBLIC_PREFIX = 'uploads/'.self::DIRECTORY;

    /**
     * @throws RuntimeException when the file cannot be written to the disk
     */
    public static function store(UploadedFile $file): string
    {
        $slug = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'client-photo';
        $stored = $file->storeAs(self::DIRECTORY, Str::limit($slug, 60, '').'-'.$file->hashName(), self::DISK);

        if (! is_string($stored) || $stored === '') {
            throw new RuntimeException('The uploaded testimonial photo could not be written to the uploads disk.');
        }

        return self::PUBLIC_PREFIX.'/'.Str::after($stored, self::DIRECTORY.'/');
    }

    /** Remove a photo this class wrote; any other path is left alone. */
    public static function delete(?string $path): void
    {
        if (is_string($path) && str_starts_with($path, self::PUBLIC_PREFIX.'/') && ! str_contains($path, '..')) {
            Storage::disk(self::DISK)->delete(Str::after($path, 'uploads/'));
        }
    }
}
