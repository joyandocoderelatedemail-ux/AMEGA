<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company that books travel for several people. Its members are ordinary
 * client accounts pointed at it, so each traveller keeps their own profile.
 *
 * Written to be safely re-run: on the shared host MySQL refused a hard foreign
 * key onto the users table, so the link is an indexed column and
 * CorporateAccount clears it when a company is deleted. Each step is skipped
 * when an earlier, partly-finished run already did it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('corporate_accounts')) {
            Schema::create('corporate_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('company_name');
                $table->string('registration_number', 100)->nullable();
                $table->string('tin', 50)->nullable();
                $table->string('industry', 150)->nullable();
                $table->string('address', 500)->nullable();
                $table->string('contact_person')->nullable();
                $table->string('contact_position', 150)->nullable();
                $table->string('contact_email')->nullable();
                $table->string('contact_phone', 50)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'corporate_account_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('corporate_account_id')->nullable()->after('account_category')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'corporate_account_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Where the database did add a foreign key, it has to go before the column can.
                try {
                    $table->dropForeign(['corporate_account_id']);
                } catch (Throwable) {
                }
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('corporate_account_id');
            });
        }

        Schema::dropIfExists('corporate_accounts');
    }
};
