<?php

use App\Enums\StatusPermohonan as S;
use App\Filament\Admin\Pages\LaporanPage;
use App\Filament\Admin\Pages\PengaturanSitusPage;
use App\Filament\Admin\Resources\PermohonanResource;
use App\Models\JenisLayanan;
use App\Models\Masyarakat;
use App\Models\Persyaratan;
use App\Models\Petugas;
use App\Services\AlurStatusService;
use App\Services\PenggunaService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    $this->sa = Skenario::user('superadmin');
    $this->admin = Skenario::user('admin');
    $this->petugas = Skenario::user('petugas');
    $this->warga = Skenario::user('warga');
});

// ------------------------------------------------------------ akses panel

it('hanya pengelola aktif yang dapat masuk panel (FR-32)', function () {
    $panel = Filament::getPanel('admin');

    expect($this->sa->canAccessPanel($panel))->toBeTrue();
    expect($this->admin->canAccessPanel($panel))->toBeTrue();
    expect($this->petugas->canAccessPanel($panel))->toBeTrue();
    expect($this->warga->canAccessPanel($panel))->toBeFalse();

    $this->petugas->update(['status_akun' => 'nonaktif']);
    expect($this->petugas->refresh()->canAccessPanel($panel))->toBeFalse();
});

// ------------------------------------------------------------ UserPolicy (BR-30)

it('administrator dapat mengelola petugas dan masyarakat tetapi tidak admin lain maupun super admin', function () {
    $adminLain = Skenario::buatUser('admin', 'admin2@uji.test');

    foreach (['view', 'update', 'delete'] as $aksi) {
        expect($this->admin->can($aksi, $this->petugas))->toBeTrue();
        expect($this->admin->can($aksi, $this->warga))->toBeTrue();
        expect($this->admin->can($aksi, $this->sa))->toBeFalse();
        expect($this->admin->can($aksi, $adminLain))->toBeFalse();
    }

    expect($this->admin->can('view', $this->admin))->toBeTrue();
    expect($this->admin->can('update', $this->admin))->toBeTrue();
    expect($this->admin->can('delete', $this->admin))->toBeFalse();
});

it('super admin dapat mengelola semua akun kecuali menghapus dirinya sendiri', function () {
    $adminLain = Skenario::buatUser('admin', 'admin2@uji.test');

    foreach ([$this->admin, $adminLain, $this->petugas, $this->warga] as $target) {
        expect($this->sa->can('view', $target))->toBeTrue();
        expect($this->sa->can('update', $target))->toBeTrue();
        expect($this->sa->can('delete', $target))->toBeTrue();
    }
    expect($this->sa->can('delete', $this->sa))->toBeFalse();
});

it('petugas dan masyarakat tidak dapat mengelola akun', function () {
    expect($this->petugas->can('viewAny', App\Models\User::class))->toBeFalse();
    expect($this->warga->can('viewAny', App\Models\User::class))->toBeFalse();
});

// ------------------------------------------------------------ master data

it('master data layanan dan persyaratan hanya untuk admin dan super admin', function () {
    expect($this->admin->can('viewAny', JenisLayanan::class))->toBeTrue();
    expect($this->sa->can('viewAny', JenisLayanan::class))->toBeTrue();
    expect($this->petugas->can('viewAny', JenisLayanan::class))->toBeFalse();
    expect($this->warga->can('viewAny', JenisLayanan::class))->toBeFalse();

    expect($this->admin->can('create', Persyaratan::class))->toBeTrue();
    expect($this->petugas->can('create', Persyaratan::class))->toBeFalse();
    expect($this->petugas->can('update', Persyaratan::first()))->toBeFalse();
});

it('halaman dan resource pengelola memeriksa peran sesuai BRD', function () {
    $this->actingAs($this->petugas);
    expect(PermohonanResource::canViewAny())->toBeTrue();
    expect(LaporanPage::canAccess())->toBeTrue();
    expect(PengaturanSitusPage::canAccess())->toBeFalse();

    foreach (['admin' => $this->admin, 'superadmin' => $this->sa] as $akun) {
        $this->actingAs($akun);
        expect(PermohonanResource::canViewAny())->toBeFalse(); // BR-05 dan BR-33
        expect(PermohonanResource::canView(Skenario::ajukanSiu()))->toBeFalse();
        expect(LaporanPage::canAccess())->toBeTrue();
        expect(PengaturanSitusPage::canAccess())->toBeTrue();
    }

    $this->actingAs($this->warga);
    expect(PermohonanResource::canViewAny())->toBeFalse();
    expect(LaporanPage::canAccess())->toBeFalse();
    expect(PengaturanSitusPage::canAccess())->toBeFalse();
});

it('tidak ada permission verifikasi yang dapat diberikan lewat UI (BR-33)', function () {
    expect(Permission::where('name', 'like', '%verif%')->exists())->toBeFalse();
    expect(Permission::where('name', 'like', '%permohonan%')->exists())->toBeFalse();
});

it('permission akun, peran, dan log dibagi sesuai kewenangan', function () {
    expect($this->admin->can('view_any_user'))->toBeTrue();
    expect($this->admin->can('view_any_role'))->toBeFalse();
    expect($this->admin->can('viewAny', Activity::class))->toBeFalse();

    expect($this->sa->can('view_any_user'))->toBeTrue();
    expect($this->sa->can('view_any_role'))->toBeTrue();
    expect($this->sa->can('delete_any_user'))->toBeTrue();
    expect($this->admin->can('delete_any_user'))->toBeFalse();

    expect($this->petugas->can('view_any_user'))->toBeFalse();
});

// ------------------------------------------------------------ PenggunaService (BR-30, 31, 32)

it('membatasi pilihan peran sesuai pelaku', function () {
    expect(PenggunaService::peranYangBolehDipilih($this->admin))->toEqual(['masyarakat', 'petugas']);
    expect(PenggunaService::peranYangBolehDipilih($this->sa))->toEqual(['masyarakat', 'petugas', 'admin', 'super_admin']);
});

it('menolak administrator menetapkan peran admin atau super admin', function () {
    foreach (['admin', 'super_admin'] as $peran) {
        expect(PenggunaService::periksa($this->admin, null, ['role' => $peran, 'status_akun' => 'aktif']))
            ->toBe('Anda tidak berwenang menetapkan peran ini.');
    }
    expect(PenggunaService::periksa($this->admin, null, ['role' => 'petugas', 'status_akun' => 'aktif']))->toBeNull();
    expect(PenggunaService::periksa($this->sa, null, ['role' => 'admin', 'status_akun' => 'aktif']))->toBeNull();
    expect(PenggunaService::periksa($this->sa, null, ['role' => 'peran_ngawur', 'status_akun' => 'aktif']))->toBe('Peran tidak valid.');
});

it('menolak pengguna mengubah peran atau menonaktifkan akunnya sendiri (BR-31)', function () {
    expect(PenggunaService::periksa($this->sa, $this->sa, ['role' => 'petugas', 'status_akun' => 'aktif']))
        ->toBe('Anda tidak dapat mengubah peran akun sendiri.');
    expect(PenggunaService::periksa($this->admin, $this->admin, ['role' => 'admin', 'status_akun' => 'nonaktif']))
        ->toBe('Anda tidak dapat menonaktifkan akun sendiri.');
    expect(PenggunaService::periksa($this->admin, $this->admin, ['role' => 'admin', 'status_akun' => 'aktif']))->toBeNull();
});

it('menolak mengubah peran akun yang sudah memiliki permohonan', function () {
    Skenario::ajukanSiu($this->warga);

    expect(PenggunaService::periksa($this->admin, $this->warga, ['role' => 'petugas', 'status_akun' => 'aktif']))
        ->toBe('Peran tidak dapat diubah karena akun ini sudah memiliki permohonan.');
    expect(PenggunaService::periksa($this->admin, $this->warga, ['role' => 'masyarakat', 'status_akun' => 'aktif']))->toBeNull();
});

it('menjaga minimal satu super admin aktif (BR-32)', function () {
    $sa2 = Skenario::buatUser('super_admin', 'sa2@uji.test', 'nonaktif');

    expect(PenggunaService::periksa($sa2, $this->sa, ['role' => 'petugas', 'status_akun' => 'aktif']))
        ->toBe('Harus ada minimal satu Super Admin aktif.');
    expect(PenggunaService::periksa($sa2, $this->sa, ['role' => 'super_admin', 'status_akun' => 'nonaktif']))
        ->toBe('Harus ada minimal satu Super Admin aktif.');
    expect(PenggunaService::alasanTidakBolehNonaktif($sa2, $this->sa))->toBe('Harus ada minimal satu Super Admin aktif.');

    $sa2->update(['status_akun' => 'aktif']);
    expect(PenggunaService::periksa($sa2, $this->sa, ['role' => 'petugas', 'status_akun' => 'aktif']))->toBeNull();
    expect(PenggunaService::alasanTidakBolehNonaktif($sa2, $this->sa))->toBeNull();
});

it('menentukan akun yang tidak boleh dihapus', function () {
    expect(PenggunaService::alasanTidakBolehDihapus($this->sa, $this->sa))->toBe('Anda tidak dapat menghapus akun sendiri.');
    expect(PenggunaService::alasanTidakBolehDihapus($this->admin, $this->sa))->toBe('Harus ada minimal satu Super Admin aktif.');

    $p = Skenario::ajukanSiu($this->warga);
    expect(PenggunaService::alasanTidakBolehDihapus($this->admin, $this->warga))
        ->toBe('Akun ini sudah memiliki permohonan. Nonaktifkan saja agar riwayat tetap utuh.');

    app(AlurStatusService::class)->ubah($p, S::DalamPemeriksaan, $this->petugas);
    expect(PenggunaService::alasanTidakBolehDihapus($this->admin, $this->petugas))
        ->toBe('Akun ini pernah memproses permohonan. Nonaktifkan saja agar riwayat tetap utuh.');

    $baru = Skenario::buatUser('petugas', 'petugasbaru@uji.test');
    expect(PenggunaService::alasanTidakBolehDihapus($this->admin, $baru))->toBeNull();
});

it('menyelaraskan tabel profil saat peran berubah', function () {
    $akun = Skenario::wargaBaru('3603190000000123');
    $svc = app(PenggunaService::class);

    $svc->terapkan($akun, ['role' => 'petugas', 'nip' => '1234']);
    expect($akun->refresh()->hasRole('petugas'))->toBeTrue();
    expect($akun->roles)->toHaveCount(1);
    expect(Masyarakat::where('user_id', $akun->id)->exists())->toBeFalse();
    expect(Petugas::find($akun->id)->nip)->toBe('1234');

    $svc->terapkan($akun, ['role' => 'masyarakat', 'nik' => '3603190000000123', 'alamat' => 'Alamat baru']);
    expect($akun->refresh()->hasRole('masyarakat'))->toBeTrue();
    expect(Petugas::where('user_id', $akun->id)->exists())->toBeFalse();
    expect(Masyarakat::find($akun->id)->alamat)->toBe('Alamat baru');
});
