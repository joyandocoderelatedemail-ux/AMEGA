<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the visa counter keeps on a BI application besides the CGAF itself:
 * the file handed over for each checklist requirement, the fee and how it is
 * paid (in full or in terms), the time filed and the processing time, and the
 * payments recorded against it. Links are indexed columns, not foreign keys:
 * the shared host's MySQL refuses them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tourist_visa_applications')) {
            Schema::table('tourist_visa_applications', function (Blueprint $table) {
                if (! Schema::hasColumn('tourist_visa_applications', 'documents')) {
                    $table->json('documents')->nullable()->after('checklist');
                }
                if (! Schema::hasColumn('tourist_visa_applications', 'total_fee')) {
                    $table->decimal('total_fee', 12, 2)->default(0)->after('photo_path');
                }
                if (! Schema::hasColumn('tourist_visa_applications', 'payment_type')) {
                    $table->string('payment_type')->default('full')->after('total_fee');
                }
                if (! Schema::hasColumn('tourist_visa_applications', 'terms_count')) {
                    $table->unsignedTinyInteger('terms_count')->nullable()->after('payment_type');
                }
                if (! Schema::hasColumn('tourist_visa_applications', 'filed_time')) {
                    $table->string('filed_time', 5)->nullable()->after('terms_count');
                }
                if (! Schema::hasColumn('tourist_visa_applications', 'processing_time')) {
                    $table->string('processing_time')->nullable()->after('filed_time');
                }
            });
        }

        if (! Schema::hasTable('tourist_visa_payments')) {
            Schema::create('tourist_visa_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tourist_visa_application_id')->index();
                $table->decimal('amount', 12, 2);
                $table->string('method');
                $table->date('paid_on');
                $table->string('reference')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tourist_visa_payments');

        if (Schema::hasTable('tourist_visa_applications')) {
            foreach (['documents', 'total_fee', 'payment_type', 'terms_count', 'filed_time', 'processing_time'] as $column) {
                if (Schema::hasColumn('tourist_visa_applications', $column)) {
                    Schema::table('tourist_visa_applications', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
