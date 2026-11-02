<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Packages saved in the configurator without a photo were given images/packages/default-package.jpg,
     * a file that never existed, so they showed as broken images. They now get the site's placeholder.
     */
    public function up(): void
    {
        DB::table('travel_packages')
            ->where('image', 'images/packages/default-package.jpg')
            ->update(['image' => 'images/og-image.jpg']);
    }

    public function down(): void
    {
        // The missing file is not worth restoring.
    }
};
