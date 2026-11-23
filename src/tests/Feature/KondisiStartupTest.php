<?php

use App\Enums\StatusPermohonan as S;
use App\Filament\Admin\Resources\PermohonanResource;
use App\Services\AlurStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

/*
 * Startup kontainer menjalankan shield:generate dan memberi role super_admin SELURUH permission
 * (terbukti pada database sungguhan: 25 izin). Tes ini meniru kondisi itu, dan bahkan memberi
 * seluruh permission kepada Administrator, untuk memastikan aturan yang terkunci di kode
 * tidak bergantung pada permission.
 */
beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->withoutVite();

    foreach (['activity', 'role', 'user', 'permohonan', 'jenis::layanan'] as $entitas) {
        foreach (['view', 'view_any', 'create', 'update', 'delete', 'delete_any'] as $aksi) {
            Permission::findOrCreate("{$aksi}_{$entitas}", 'web');
        }
    }
    foreach (['page_LaporanPage', 'page_PengaturanSitusPage', 'widget_PermohonanTerbaruWidget'] as $nama) {
        Permission::findOrCreate($nama, 'web');
    }
});

function berikanSemuaIzin(string $role): void
{
    Role::findByName($role, 'web')->syncPermissions(Permission::all());
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('Super Admin yang memegang seluruh permission tetap tidak dapat mengubah atau menghapus log aktivitas', function () {
    berikanSemuaIzin('super_admin');
    $sa = Skenario::user('superadmin')->fresh();
    $log = activity('Uji')->log('contoh');

    expect($sa->can('delete_activity'))->toBeTrue(); // prasyarat: kondisi startup memang terpenuhi
    expect($sa->can('viewAny', Activity::class))->toBeTrue();
    expect($sa->can('view', $log))->toBeTrue();

    foreach (['update', 'delete', 'forceDelete'] as $aksi) {
        expect($sa->can($aksi, $log))->toBeFalse();
    }
    foreach (['create', 'deleteAny'] as $aksi) {
        expect($sa->can($aksi, Activity::class))->toBeFalse();
    }
});

it('Administrator yang memegang seluruh permission tetap tidak dapat melihat log aktivitas', function () {
    berikanSemuaIzin('admin');
    $admin = Skenario::user('admin')->fresh();

    expect($admin->can('view_any_activity'))->toBeTrue(); // prasyarat
    expect($admin->can('viewAny', Activity::class))->toBeFalse();
});

it('seluruh permission tidak memberi hak memverifikasi kepada Administrator maupun Super Admin (BR-05, BR-33)', function (string $peran, string $role) {
    berikanSemuaIzin($role);
    $akun = Skenario::user($peran)->fresh();
    $p = Skenario::ajukanSiu();

    expect(fn () => app(AlurStatusService::class)->ubah($p, S::DalamPemeriksaan, $akun))
        ->toThrow(AuthorizationException::class);
    expect($p->refresh()->status)->toBe(S::Diajukan);

    $this->actingAs($akun);
    expect(PermohonanResource::canViewAny())->toBeFalse();
    expect(PermohonanResource::canView($p))->toBeFalse();

    $this->get(route('filament.admin.resources.permohonan.index'))->assertForbidden();
    $this->get(route('filament.admin.resources.permohonan.view', ['record' => $p->id]))->assertForbidden();
    $this->get(route('dokumen.tampil', $p->dokumen()->first()))->assertForbidden();
})->with([
    'administrator' => ['admin', 'admin'],
    'super admin' => ['superadmin', 'super_admin'],
]);

it('seluruh permission tidak membuka master data bagi Petugas', function () {
    berikanSemuaIzin('petugas');
    $petugas = Skenario::user('petugas')->fresh();

    expect($petugas->can('viewAny', App\Models\JenisLayanan::class))->toBeFalse();
    expect($petugas->can('create', App\Models\Persyaratan::class))->toBeFalse();
});
