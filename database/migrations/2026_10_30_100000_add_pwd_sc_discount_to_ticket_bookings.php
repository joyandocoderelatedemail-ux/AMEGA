<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PWD / Senior Citizen is a discount on some of the passengers, not a passenger type: how many of
 * them, the rate (%) or a peso amount per person, and the amount taken off the total. Bookings that priced PWD / SC as a fare of
 * its own keep that fare row and their totals; their count is carried over as the discount count
 * (with no rate, so nothing is taken off twice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_bookings', 'pwd_sc_count')) {
                $table->unsignedSmallInteger('pwd_sc_count')->default(0)->after('fare_breakdown');
                $table->decimal('pwd_sc_rate', 5, 2)->nullable()->after('pwd_sc_count');
                $table->decimal('pwd_sc_amount', 12, 2)->nullable()->after('pwd_sc_rate');
                $table->decimal('pwd_sc_discount', 12, 2)->default(0)->after('pwd_sc_amount');
            }
        });

        DB::table('ticket_bookings')->whereNotNull('fare_breakdown')->orderBy('id')->each(function ($booking) {
            $count = (int) (json_decode((string) $booking->fare_breakdown, true)['pwd_sc']['qty'] ?? 0);
            if ($count > 0) {
                DB::table('ticket_bookings')->where('id', $booking->id)->update(['pwd_sc_count' => $count]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn(['pwd_sc_count', 'pwd_sc_rate', 'pwd_sc_amount', 'pwd_sc_discount']);
        });
    }
};
