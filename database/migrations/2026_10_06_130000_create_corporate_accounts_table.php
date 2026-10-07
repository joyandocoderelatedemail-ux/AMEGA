<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company that books travel for several people. Its members are ordinary
 * client accounts pointed at it, so each traveller keeps their own profile.
 */
return new class extends Migration
{
    public function up(): void
    {
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

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('corporate_account_id')->nullable()->after('account_category')
                ->constrained('corporate_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corporate_account_id');
        });

        Schema::dropIfExists('corporate_accounts');
    }
};
