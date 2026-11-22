<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Empat peran sistem (BRD): masyarakat, petugas, admin, super_admin.
     */
    public function run(): void
    {
        foreach (['masyarakat', 'petugas', 'admin', 'super_admin'] as $nama) {
            Role::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }
    }
}
