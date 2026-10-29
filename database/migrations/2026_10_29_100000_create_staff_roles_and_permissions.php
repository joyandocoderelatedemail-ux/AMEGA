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
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->json('permissions');
            // The roles every install starts with: editable, never deleted.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('staff_role_id')->nullable()->after('allowed_pages')->constrained('staff_roles')->nullOnDelete();
            // Extra permissions on top of the role. Null: still set up the old way (allowed_pages).
            $table->json('permissions')->nullable()->after('staff_role_id');
        });

        $now = now();
        foreach (Permissions::systemRoles() as $name => $role) {
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
            $table->dropConstrainedForeignId('staff_role_id');
            $table->dropColumn('permissions');
        });

        Schema::dropIfExists('staff_roles');
    }
};
