<?php

use App\Models\VisaPricingTier;
use Database\Seeders\VisaPricingSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Load the Visa Assistance Fee Breakdown (revised August 1, 2026) so the
     * live counter gets the confirmed per-country prices. The two placeholder
     * visit visa rows it replaces are removed.
     */
    public function up(): void
    {
        VisaPricingTier::where('service_type', 'visit_visa')
            ->whereIn('label', ['Visit Visa — Regular', 'Visit Visa — Australia & New Zealand'])
            ->delete();

        (new VisaPricingSeeder)->run();
    }

    /**
     * Prices are data, not schema; leave them in place.
     */
    public function down(): void {}
};
