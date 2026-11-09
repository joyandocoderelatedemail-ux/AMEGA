<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Photos for destinations, uploaded from the admin. Like package photos they are shown to anyone on the
 * public site, so they live on the web-served uploads disk. Every path handed back by store() is relative
 * to the public directory ("uploads/destinations/..."), so views can pass it straight to WebImage::url().
 */
class DestinationImageStorage
{
    public const DISK = 'uploads';

    public const DIRECTORY = 'destinations';

    /** The prefix every managed path carries once it reaches the database. */
    public const PUBLIC_PREFIX = 'uploads/'.self::DIRECTORY;

    /**
     * Write an uploaded photo and return its public-relative path.
     *
     * @throws RuntimeException when the file cannot be written to the disk
     */
    public static function store(UploadedFile $file): string
    {
        $stored = $file->storeAs(self::DIRECTORY, self::filenameFor($file), self::DISK);

        if (! is_string($stored) || $stored === '') {
            throw new RuntimeException('The uploaded destination photo could not be written to the uploads disk.');
        }

        return self::PUBLIC_PREFIX.'/'.Str::after($stored, self::DIRECTORY.'/');
    }

    /**
     * Remove a photo this class wrote. Paths pointing anywhere else (the photos that ship with the site
     * under newassets/, or a remote address) are left alone, so replacing a photo never deletes
     * something the application did not upload.
     */
    public static function delete(?string $path): void
    {
        if (! self::owns($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete(Str::after($path, 'uploads/'));
    }

    public static function owns(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, self::PUBLIC_PREFIX.'/')
            && ! str_contains($path, '..');
    }

    /** A readable, unguessable name: the slugged original plus a random part. */
    private static function filenameFor(UploadedFile $file): string
    {
        $slug = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'destination-photo';

        return Str::limit($slug, 60, '').'-'.$file->hashName();
    }
}
