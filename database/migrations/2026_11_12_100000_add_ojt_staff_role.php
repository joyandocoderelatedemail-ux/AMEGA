<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The OJT (on-the-job trainee) role, for installs that already have the staff roles.
     * It starts with no permissions; an admin ticks them at Users > Roles & Permissions.
     */
    public function up(): void
    {
        if (! Schema::hasTable('staff_roles') || DB::table('staff_roles')->where('name', 'OJT')->exists()) {
            return;
        }

        $role = Permissions::systemRoles()['OJT'];
        DB::table('staff_roles')->insert([
            'name' => 'OJT',
            'description' => $role['description'],
            'permissions' => json_encode(Permissions::clean($role['permissions'])),
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Only while nobody holds it.
        $id = DB::table('staff_roles')->where('name', 'OJT')->value('id');
        if ($id && ! DB::table('users')->where('staff_role_id', $id)->exists()) {
            DB::table('staff_roles')->where('id', $id)->delete();
        }
    }
};
