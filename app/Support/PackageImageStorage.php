<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Public marketing photos for travel packages.
 *
 * Package photos are shown to anonymous visitors on the public site, so unlike
 * client documents (see DocumentStorage) they belong in a web-served folder.
 * Every path handed back by store() is relative to the public directory, which
 * means views can pass it straight to asset().
 */
class PackageImageStorage
{
    /**
     * The disk configured in config/filesystems.php, rooted at the public
     * uploads folder.
     */
    public const DISK = 'uploads';

    /**
     * Folder inside the disk that package photos are written to.
     */
    public const DIRECTORY = 'packages';

    /**
     * The prefix every managed path carries once it reaches the database.
     */
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
            throw new RuntimeException('The uploaded package photo could not be written to the uploads disk.');
        }

        return self::PUBLIC_PREFIX.'/'.Str::after($stored, self::DIRECTORY.'/');
    }

    /**
     * Remove a photo this class previously wrote.
     *
     * Paths pointing anywhere else - seeded assets under newassets/, hand-typed
     * paths, remote URLs - are left untouched, so replacing a photo never
     * deletes something the application did not upload.
     */
    public static function delete(?string $path): void
    {
        if (! self::owns($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete(self::relativePath($path));
    }

    /**
     * Whether a stored path points at a file this class manages.
     */
    public static function owns(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::PUBLIC_PREFIX.'/');
    }

    /**
     * A readable, unguessable filename for an uploaded photo.
     *
     * The client-supplied name never reaches the filesystem; only a slugged
     * version of it survives, and a random suffix keeps the name unique.
     */
    protected static function filenameFor(UploadedFile $file): string
    {
        $slug = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($slug === '') {
            $slug = 'package-photo';
        }

        return Str::limit($slug, 60, '').'-'.$file->hashName();
    }

    /**
     * Translate a public-relative path back to a path on the disk.
     *
     * The disk is rooted at "public/uploads", so "uploads/packages/a.jpg"
     * becomes "packages/a.jpg".
     */
    protected static function relativePath(string $path): string
    {
        return Str::after($path, 'uploads/');
    }
}
