<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HakAksesSeeder extends Seeder
{
    /**
     * Permission awal per peran. Super Admin dapat menyesuaikannya lewat halaman Peran (FR-47).
     * givePermissionTo bersifat menambah, jadi perubahan lewat UI tidak tertimpa saat seeder diulang.
     *
     * Dikunci di kode, BUKAN permission:
     * - hak memverifikasi permohonan (AlurStatusService, PermohonanResource), BR-05 dan BR-33;
     * - akses log aktivitas (LogAktivitasPolicy), hanya lihat dan hanya Super Admin.
     */
    public function run(): void
    {
        $akunAdmin = ['view_any_user', 'view_user', 'create_user', 'update_user', 'delete_user'];
        $akunSuper = array_merge($akunAdmin, ['delete_any_user']);
        $role = ['view_any_role', 'view_role', 'create_role', 'update_role', 'delete_role', 'delete_any_role'];

        foreach (array_merge($akunSuper, $role) as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        Role::findByName('admin', 'web')->givePermissionTo($akunAdmin);
        Role::findByName('super_admin', 'web')->givePermissionTo(array_merge($akunSuper, $role));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
