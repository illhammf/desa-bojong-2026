<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HakAksesSeeder extends Seeder
{
    /**
     * Permission awal per peran. Super Admin dapat menyesuaikannya lewat halaman Role (FR-47).
     * givePermissionTo bersifat menambah, jadi perubahan lewat UI tidak tertimpa saat seeder diulang.
     *
     * Hak memverifikasi permohonan TIDAK dibuat sebagai permission: aturannya ada di kode
     * (AlurStatusService dan PermohonanResource) agar BR-05 dan BR-33 tidak bisa dilonggarkan lewat UI.
     */
    public function run(): void
    {
        $akunAdmin = ['view_any_user', 'view_user', 'create_user', 'update_user', 'delete_user'];
        $akunSuper = array_merge($akunAdmin, ['delete_any_user']);
        $role = ['view_any_role', 'view_role', 'create_role', 'update_role', 'delete_role', 'delete_any_role'];
        $log = ['view_any_activity', 'view_activity'];

        foreach (array_merge($akunSuper, $role, $log) as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        Role::findByName('admin', 'web')->givePermissionTo($akunAdmin);
        Role::findByName('super_admin', 'web')->givePermissionTo(array_merge($akunSuper, $role, $log));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
