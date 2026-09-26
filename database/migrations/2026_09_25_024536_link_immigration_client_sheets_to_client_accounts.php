<?php

use App\Models\ImmigrationClient;
use App\Services\ClientAccountService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Every client sheet belongs to a client account, so the client appears in
     * the admin client list. Sheets keyed in before sheets were linked get
     * their account now (an existing one is reused when it matches).
     */
    public function up(): void
    {
        ImmigrationClient::whereNull('user_id')->each(function (ImmigrationClient $sheet) {
            $sheet->update(['user_id' => ClientAccountService::clientForSheet($sheet)->id]);
        });
    }

    /**
     * The links are data, not schema; leave them in place.
     */
    public function down(): void {}
};
