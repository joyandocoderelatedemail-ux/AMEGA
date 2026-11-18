<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Our Core Services, in the order the agency wants them shown. */
    private const ORDER = [
        'Travel Services' => 1,
        'Local Tour Services' => 2,
        'Cruise Services' => 3,
        'Visa Assistance' => 4,
        'Passport Assistance' => 5,
        'Immigration Services' => 6,
        'PRA - Retirement Visa' => 7,
        'Documentation Services' => 8,
    ];

    public function up(): void
    {
        foreach (self::ORDER as $title => $order) {
            DB::table('services')->where('title', $title)->update(['order' => $order]);
        }
    }

    public function down(): void
    {
        // Display order only; it can be changed again from the admin Services page.
    }
};
