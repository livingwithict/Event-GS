<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Six named roles replace the old set. `admin` was global and unrestricted, so
 * it becomes Super Admin; `manager` was already scoped to assigned events, which
 * is exactly Event Admin.
 */
return new class extends Migration
{
    private array $map = [
        'admin' => 'super_admin',
        'manager' => 'event_admin',
        'scanner' => 'scanner_staff',
    ];

    public function up(): void
    {
        foreach ($this->map as $old => $new) {
            DB::table('users')->where('role', $old)->update(['role' => $new]);
        }
    }

    public function down(): void
    {
        // super_admin is deliberately not reverted: former `admin` users are now
        // indistinguishable from original super admins, and demoting a real
        // super admin would lock everyone out.
        DB::table('users')->where('role', 'event_admin')->update(['role' => 'manager']);
        DB::table('users')->where('role', 'scanner_staff')->update(['role' => 'scanner']);
    }
};
