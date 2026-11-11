<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Local Tours desk: a booking per client and tour (Puning Hot Spring, Corregidor, Angeles City,
 * Camayan...), worked through the booking procedure's steps, checked by an approver, paid at the
 * cashier, then released with a voucher. Also its payments, its scans (guest IDs, the supplier's
 * confirmation, proof of payment), and the "Local Tours Agent" staff role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_tour_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('tour', 40);
            // No foreign keys to users: the production users table's engine refuses them (errno 150).
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 30)->default('availability');

            $table->string('lead_guest_name');
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->string('children_ages')->nullable();
            $table->date('travel_date')->nullable();
            $table->string('pickup_time', 20)->nullable();
            $table->string('pickup_location')->nullable();
            $table->string('dropoff_location')->nullable();
            $table->text('attractions')->nullable();
            $table->text('special_requests')->nullable();

            // What the client is quoted and has paid (pesos), and what the supplier charges AMEGA.
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->timestamp('payment_in_full_at')->nullable();
            $table->decimal('supplier_cost', 12, 2)->nullable();
            $table->decimal('supplier_paid_amount', 12, 2)->nullable();
            $table->string('supplier_payment_reference', 100)->nullable();
            $table->string('supplier_confirmation', 100)->nullable();

            // Transport: an AMEGA driver, the supplier's own, or the guests' own.
            $table->string('transport', 20)->default('amega');
            $table->string('driver', 20)->nullable();
            $table->string('vehicle', 100)->nullable();

            // The steps ticked off so far (keys from LocalTourBooking::tasks()).
            $table->json('checklist')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('agreement_at')->nullable();
            $table->timestamp('voucher_released_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->string('approval_status')->nullable();
            $table->timestamp('approval_requested_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['tour', 'status']);
            $table->index('approval_status');
        });

        Schema::create('local_tour_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_tour_booking_id')->constrained('local_tour_bookings')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('local_tour_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_tour_booking_id')->constrained('local_tour_bookings')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });

        // The desk's own role, for installs that already have the staff roles.
        if (Schema::hasTable('staff_roles') && ! DB::table('staff_roles')->where('name', 'Local Tours Agent')->exists()) {
            $role = Permissions::systemRoles()['Local Tours Agent'];
            DB::table('staff_roles')->insert([
                'name' => 'Local Tours Agent',
                'description' => $role['description'],
                'permissions' => json_encode(Permissions::clean($role['permissions'])),
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('local_tour_documents');
        Schema::dropIfExists('local_tour_payments');
        Schema::dropIfExists('local_tour_bookings');

        if (Schema::hasTable('staff_roles')) {
            DB::table('staff_roles')->where('name', 'Local Tours Agent')->delete();
        }
    }
};
