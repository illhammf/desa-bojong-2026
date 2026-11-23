<?php

use App\Models\JenisLayanan;
use App\Models\PengaturanSitus;
use App\Models\Permohonan;
use App\Models\Persyaratan;
use App\Models\User;
use Database\Seeders\JenisLayananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

it('menyiapkan data awal sesuai SRS dan rancangan', function () {
    expect(JenisLayanan::count())->toBe(20);
    expect(Persyaratan::count())->toBe(51);
    expect(PengaturanSitus::count())->toBe(1);
    expect(Role::pluck('name')->sort()->values()->all())->toEqual(['admin', 'masyarakat', 'petugas', 'super_admin']);
    expect(User::count())->toBe(4);
    expect(Permohonan::count())->toBe(0); // data contoh hanya untuk lingkungan local
    expect(Activity::count())->toBe(0);
});

it('memiliki kode layanan unik yang sama dengan daftar seeder', function () {
    $kodeSeeder = collect(JenisLayananSeeder::DAFTAR)->pluck(0)->sort()->values()->all();

    expect(JenisLayanan::pluck('kode')->sort()->values()->all())->toEqual($kodeSeeder);
    expect(JenisLayanan::where('aktif', false)->count())->toBe(0);
});

it('tidak mewajibkan persyaratan yang masih menunggu konfirmasi desa', function () {
    foreach (['SKTM', 'BDN', 'KRM', 'UMM'] as $kode) {
        $layanan = JenisLayanan::where('kode', $kode)->firstOrFail();
        expect($layanan->persyaratan()->count())->toBeGreaterThan(0);
        expect($layanan->persyaratan()->where('wajib', true)->count())->toBe(0);
    }

    $suratPindah = Persyaratan::where('nama_persyaratan', 'Surat Pindah')->firstOrFail();
    expect($suratPindah->wajib)->toBeFalse();
});

it('aman dijalankan ulang: tidak menggandakan data dan tidak menimpa perubahan admin', function () {
    JenisLayanan::where('kode', 'SIU')->update(['estimasi_waktu' => '3 Hari Kerja']);
    PengaturanSitus::first()->update(['nama_situs' => 'Nama Ubahan Admin']);

    $this->seed();

    expect(JenisLayanan::count())->toBe(20);
    expect(Persyaratan::count())->toBe(51);
    expect(PengaturanSitus::count())->toBe(1);
    expect(User::count())->toBe(4);
    expect(JenisLayanan::where('kode', 'SIU')->value('estimasi_waktu'))->toBe('3 Hari Kerja');
    expect(PengaturanSitus::first()->nama_situs)->toBe('Nama Ubahan Admin');
});

it('menyiapkan satu super admin aktif, satu admin, satu petugas, dan satu warga berprofil', function () {
    expect(User::role('super_admin')->where('status_akun', 'aktif')->count())->toBe(1);
    expect(User::role('admin')->count())->toBe(1);
    expect(User::role('petugas')->count())->toBe(1);
    expect(User::role('masyarakat')->count())->toBe(1);

    expect(User::role('admin')->first()->admin)->not->toBeNull();
    expect(User::role('petugas')->first()->petugas)->not->toBeNull();
    expect(User::role('masyarakat')->first()->masyarakat->nik)->toBe('3603192008850003');
    expect(User::role('super_admin')->first()->admin)->toBeNull();
});
