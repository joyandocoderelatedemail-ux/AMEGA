<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every immigration case filed by a representative names AMEGA's own: Aurora M. Agustin, BI
 * accreditation T.A 202623914-2. Cases saved earlier with another name or none take hers.
 */
return new class extends Migration
{
    private const NAME = 'Aurora M. Agustin';

    private const ACCREDITATION = 'T.A 202623914-2';

    public function up(): void
    {
        if (Schema::hasTable('immigration_petitions')) {
            DB::table('immigration_petitions')->whereNotNull('details')->orderBy('id')->each(function (object $row): void {
                $details = json_decode((string) $row->details, true);
                if (! is_array($details) || empty($details['by_representative'])) {
                    return;
                }

                $details['representative_name'] = self::NAME;
                $details['accreditation_no'] = self::ACCREDITATION;
                ksort($details);

                DB::table('immigration_petitions')->where('id', $row->id)->update(['details' => json_encode($details)]);
            });
        }

        if (Schema::hasTable('immigration_overstays')) {
            DB::table('immigration_overstays')->where('by_representative', true)->update([
                'representative_name' => self::NAME,
                'representative_accreditation_no' => self::ACCREDITATION,
            ]);
        }
    }

    public function down(): void
    {
        // The names they replaced are not kept.
    }
};
