<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Single source of truth for where private client documents live.
 *
 * Passport scans, government IDs, birth certificates and client photos used to
 * be written to the "public" disk, which is symlinked into the web root and
 * served by the web server with no authentication. They now go here instead and
 * leave only through a controller that has authorised the request.
 *
 * The disk is configurable so production can move to a private S3 bucket
 * without touching application code.
 */
class DocumentStorage
{
    /**
     * The configured private document disk.
     */
    public static function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.documents_disk', 'local'));
    }

    /**
     * The disk name, for the few places that need to pass it on.
     */
    public static function diskName(): string
    {
        return config('filesystems.documents_disk', 'local');
    }
}
