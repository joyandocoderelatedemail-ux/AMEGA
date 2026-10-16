<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * International carriers for the wizard's airline search. They carry no booking
 * link; the desk can add one from the airlines page if it wants one.
 */
return new class extends Migration
{
    private const AIRLINES = [
        ['Singapore Airlines', 'SQ'],
        ['Qatar Airways', 'QR'],
        ['Cathay Pacific Airways', 'CX'],
        ['All Nippon Airways', 'NH'],
        ['Emirates', 'EK'],
        ['American Airlines', 'AA'],
        ['United Airlines', 'UA'],
        ['Delta Air', 'DL'],
        ['Eva Air', 'BR'],
        ['Etihad Airways', 'EY'],
        ['Thai Airways', 'TG'],
        ['Air New Zealand', 'NZ'],
        ['Qantas', 'QF'],
        ['Swiss International Airlines', 'LX'],
        ['British Airways', 'BA'],
        ['JetBlue Airways', 'B6'],
        ['Vietnam Airlines', 'VN'],
        ['Japan Airlines', 'JL'],
    ];

    public function up(): void
    {
        $now = now();
        $order = (int) DB::table('airlines')->max('sort_order');

        foreach (self::AIRLINES as [$name, $code]) {
            if (DB::table('airlines')->where('code', $code)->orWhere('name', $name)->exists()) {
                continue;
            }

            DB::table('airlines')->insert([
                'name' => $name,
                'code' => $code,
                'booking_url' => null,
                'is_active' => true,
                'sort_order' => ++$order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('airlines')
            ->whereIn('code', array_column(self::AIRLINES, 1))
            ->whereNull('booking_url')
            ->delete();
    }
};
