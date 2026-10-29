<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and permissions. A staff role is a named set of permissions (see App\Support\Permissions);
 * each staff member may have one, plus extra permissions of their own. Accounts set up the old way
 * (ticked pages) keep working exactly as before until an admin gives them a role.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run again: a first run on production stopped half way (see below).
        if (! Schema::hasTable('staff_roles')) {
            Schema::create('staff_roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->json('permissions');
                // The roles every install starts with: editable, never deleted.
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        // No foreign key: the production users table's engine refuses one (errno 150). A role still
        // held by someone cannot be deleted (AdminStaffRoleController::destroy), so none is needed.
        if (! Schema::hasColumn('users', 'staff_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('staff_role_id')->nullable()->after('allowed_pages')->index();
            });
        }
        if (! Schema::hasColumn('users', 'permissions')) {
            Schema::table('users', function (Blueprint $table) {
                // Extra permissions on top of the role. Null: still set up the old way (allowed_pages).
                $table->json('permissions')->nullable()->after('staff_role_id');
            });
        }

        $now = now();
        foreach (Permissions::systemRoles() as $name => $role) {
            if (DB::table('staff_roles')->where('name', $name)->exists()) {
                continue;
            }
            DB::table('staff_roles')->insert([
                'name' => $name,
                'description' => $role['description'],
                'permissions' => json_encode(Permissions::clean($role['permissions'])),
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['staff_role_id']);
            $table->dropColumn(['staff_role_id', 'permissions']);
        });

        Schema::dropIfExists('staff_roles');
    }
};
