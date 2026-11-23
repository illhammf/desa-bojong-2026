<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->withoutVite();
});

it('mengarahkan pengunjung ke login panel', function () {
    $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
});

it('menolak warga dan akun nonaktif masuk panel', function () {
    $this->actingAs(Skenario::user('warga'))->get('/admin')->assertForbidden();

    $petugas = Skenario::user('petugas');
    $petugas->update(['status_akun' => 'nonaktif']);
    $this->actingAs($petugas)->get('/admin')->assertForbidden();
});

it('mengatur akses halaman panel sesuai peran', function (string $peran, string $rute, int $status) {
    $this->actingAs(Skenario::user($peran))->get(route($rute))->assertStatus($status);
})->with([
    'dashboard petugas' => ['petugas', 'filament.admin.pages.dashboard', 200],
    'dashboard admin' => ['admin', 'filament.admin.pages.dashboard', 200],
    'dashboard super admin' => ['superadmin', 'filament.admin.pages.dashboard', 200],

    'daftar permohonan petugas' => ['petugas', 'filament.admin.resources.permohonan.index', 200],
    'daftar permohonan admin' => ['admin', 'filament.admin.resources.permohonan.index', 403],
    'daftar permohonan super admin' => ['superadmin', 'filament.admin.resources.permohonan.index', 403],

    'laporan petugas' => ['petugas', 'filament.admin.pages.laporan', 200],
    'laporan admin' => ['admin', 'filament.admin.pages.laporan', 200],
    'laporan super admin' => ['superadmin', 'filament.admin.pages.laporan', 200],

    'pengaturan situs petugas' => ['petugas', 'filament.admin.pages.pengaturan-situs', 403],
    'pengaturan situs admin' => ['admin', 'filament.admin.pages.pengaturan-situs', 200],
    'pengaturan situs super admin' => ['superadmin', 'filament.admin.pages.pengaturan-situs', 200],

    'jenis layanan petugas' => ['petugas', 'filament.admin.resources.jenis-layanan.index', 403],
    'jenis layanan admin' => ['admin', 'filament.admin.resources.jenis-layanan.index', 200],
    'jenis layanan super admin' => ['superadmin', 'filament.admin.resources.jenis-layanan.index', 200],

    'kelola akun petugas' => ['petugas', 'filament.admin.resources.users.index', 403],
    'kelola akun admin' => ['admin', 'filament.admin.resources.users.index', 200],
    'kelola akun super admin' => ['superadmin', 'filament.admin.resources.users.index', 200],
]);

it('petugas dapat membuka detail permohonan, administrator dan super admin tidak', function () {
    $p = Skenario::ajukanSiu();

    $this->actingAs(Skenario::user('petugas'))
        ->get(route('filament.admin.resources.permohonan.view', ['record' => $p->id]))
        ->assertOk()
        ->assertSee($p->nomor_permohonan);

    $this->actingAs(Skenario::user('admin'))
        ->get(route('filament.admin.resources.permohonan.view', ['record' => $p->id]))
        ->assertForbidden();

    $this->actingAs(Skenario::user('superadmin'))
        ->get(route('filament.admin.resources.permohonan.view', ['record' => $p->id]))
        ->assertForbidden();
});

it('administrator tidak dapat membuka akun super admin lewat URL langsung', function () {
    $sa = Skenario::user('superadmin');

    $this->actingAs(Skenario::user('admin'))
        ->get(route('filament.admin.resources.users.edit', ['record' => $sa->id]))
        ->assertNotFound();
});

it('halaman laporan menampilkan rekap dan rincian permohonan', function () {
    Skenario::ajukanSiu();
    Skenario::ajukanSiu();

    $this->actingAs(Skenario::user('petugas'))
        ->get(route('filament.admin.pages.laporan'))
        ->assertOk()
        ->assertSee('Rekapitulasi per Jenis Layanan')
        ->assertSee('Surat Ijin Usaha')
        ->assertSee('REG-SIU-');
});
