<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agreements used to start with a 0.00 price line that staff often left
     * as is, so they print and email a 0.00 total for a ticket that has a
     * price. Give those the ticket's price. Agreements staff priced are not
     * touched.
     */
    public function up(): void
    {
        $unpriced = DB::table('booking_agreements')
            ->join('ticket_bookings', 'ticket_bookings.id', '=', 'booking_agreements.ticket_booking_id')
            ->where('booking_agreements.total_amount', 0)
            ->where('ticket_bookings.total_amount', '>', 0)
            ->select('booking_agreements.id', 'booking_agreements.pricing_items', 'ticket_bookings.total_amount as ticket_total')
            ->get();

        foreach ($unpriced as $agreement) {
            $items = json_decode((string) $agreement->pricing_items, true) ?: [];

            if (collect($items)->sum(fn ($item): float => (float) ($item['amount'] ?? 0)) > 0) {
                continue;
            }

            $total = round((float) $agreement->ticket_total, 2);

            if ($items === []) {
                $items[] = ['airfare_description' => 'Airfare', 'price_details' => null, 'pax_count' => 1];
            }

            $items[0]['amount'] = $total;

            DB::table('booking_agreements')->where('id', $agreement->id)->update([
                'pricing_items' => json_encode($items),
                'total_amount' => $total,
            ]);
        }
    }

    /**
     * The prices filled in are indistinguishable from ones staff enter later,
     * so this is not reversed.
     */
    public function down(): void {}
};
