<?php

use App\Enums\StatusPermohonan as S;
use App\Models\JenisLayanan;
use App\Models\Persyaratan;
use App\Models\User;
use App\Services\AlurStatusService;
use App\Services\PenggunaService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Skenario;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
});

it('seeding data awal tidak meninggalkan log aktivitas', function () {
    expect(Activity::count())->toBe(0);
});

it('mencatat login dengan pelaku, IP, dan perangkat', function () {
    $warga = Skenario::user('warga');
    event(new Login('web', $warga, false));

    $a = Skenario::logTerakhir('Autentikasi', 'login');
    expect($a)->not->toBeNull();
    expect($a->causer_id)->toBe($warga->id);
    expect($a->properties->toArray())->toHaveKeys(['ip', 'perangkat', 'peran']);
});

it('mencatat login gagal tanpa menyimpan kata sandi', function () {
    event(new Failed('web', null, ['email' => 'penyusup@uji.test', 'password' => 'RAHASIA123']));

    $a = Skenario::logTerakhir('Autentikasi', 'login_gagal');
    expect($a)->not->toBeNull();
    expect($a->properties['email'])->toBe('penyusup@uji.test');
    expect($a->properties->toJson())->not->toContain('RAHASIA123');
});

it('mencatat logout', function () {
    $warga = Skenario::user('warga');
    event(new Logout('web', $warga));

    expect(Skenario::logTerakhir('Autentikasi', 'logout')?->causer_id)->toBe($warga->id);
});

it('mencatat perubahan layanan dengan nilai lama dan baru', function () {
    $layanan = JenisLayanan::where('kode', 'UMM')->firstOrFail();
    $layanan->update(['estimasi_waktu' => '2 Hari Kerja']);

    $a = Skenario::logTerakhir('Layanan', 'updated');
    expect($a->subject_id)->toBe($layanan->id);
    expect($a->properties['attributes']['estimasi_waktu'])->toBe('2 Hari Kerja');
    expect($a->properties['old'])->toHaveKey('estimasi_waktu');
});

it('mencatat perubahan persyaratan tetapi tidak mencatat perubahan urutan saja', function () {
    $syarat = Persyaratan::where('nama_persyaratan', 'KTP')->orderBy('id')->firstOrFail();
    $syarat->update(['wajib' => false, 'tipe_persyaratan' => 'isian']);

    $a = Skenario::logTerakhir('Persyaratan', 'updated');
    expect($a->subject_id)->toBe($syarat->id);
    expect($a->properties['attributes'])->toHaveKeys(['wajib', 'tipe_persyaratan']);

    $sebelum = Activity::count();
    $syarat->update(['urutan' => 99]);
    expect(Activity::count())->toBe($sebelum);
});

it('mencatat akun baru tanpa kata sandi dan hanya perubahan atribut yang relevan', function () {
    $akun = User::create(['name' => 'Warga Audit', 'email' => 'audit@uji.test', 'no_hp' => '081200000055', 'status_akun' => 'aktif', 'password' => 'rahasia-audit']);
    expect(Skenario::logTerakhir('Akun', 'created')?->subject_id)->toBe($akun->id);

    $sebelum = Activity::count();
    $akun->update(['password' => 'rahasia-baru-123']);
    $akun->forceFill(['remember_token' => Str::random(10)])->save();
    expect(Activity::count())->toBe($sebelum);

    $akun->update(['no_hp' => '081200000066']);
    expect(Activity::count())->toBe($sebelum + 1);

    $bocor = Activity::where('log_name', 'Akun')->get()->contains(fn ($x) => str_contains($x->properties->toJson(), 'password'));
    expect($bocor)->toBeFalse();
});

it('mencatat perubahan peran dengan pelaku super admin', function () {
    $sa = Skenario::user('superadmin');
    $akun = Skenario::buatUser('masyarakat', 'audit2@uji.test');

    $this->actingAs($sa);
    app(PenggunaService::class)->terapkan($akun->refresh(), ['role' => 'petugas', 'nip' => '1234']);

    $a = Skenario::logTerakhir('Akun', 'peran');
    expect($a)->not->toBeNull();
    expect($a->causer_id)->toBe($sa->id);
    expect($a->properties['dari'])->toBe('masyarakat');
    expect($a->properties['ke'])->toBe('petugas');
});

it('mencatat pengajuan baru dengan pelaku pemohon', function () {
    $p = Skenario::ajukanSiu();

    $a = Skenario::logTerakhir('Permohonan', 'diajukan');
    expect($a)->not->toBeNull();
    expect($a->causer_id)->toBe($p->user_id);
    expect($a->properties['nomor'])->toBe($p->nomor_permohonan);
});

it('mencatat perubahan status oleh petugas dan perbaikan oleh pemohon', function () {
    $petugas = Skenario::user('petugas');
    $p = Skenario::ajukanSiu();
    $alur = app(AlurStatusService::class);

    $alur->ubah($p, S::DalamPemeriksaan, $petugas);
    $alur->ubah($p->refresh(), S::PerluPerbaikan, $petugas, 'Foto KK buram, mohon unggah ulang.');

    $a = Skenario::logTerakhir('Permohonan', 'status');
    expect($a->causer_id)->toBe($petugas->id);
    expect($a->properties['dari'])->toBe('dalam_pemeriksaan');
    expect($a->properties['ke'])->toBe('perlu_perbaikan');

    $alur->ubah($p->refresh(), S::DalamPemeriksaan, null, 'Pemohon mengirim perbaikan.');
    expect(Skenario::logTerakhir('Permohonan', 'status')->causer_id)->toBe($p->user_id);
});

it('perubahan status yang ditolak tidak meninggalkan log', function () {
    $p = Skenario::ajukanSiu();
    $sebelum = Activity::count();

    try {
        app(AlurStatusService::class)->ubah($p->refresh(), S::SiapDiambil, Skenario::user('petugas'));
    } catch (Throwable) {
    }

    expect(Activity::count())->toBe($sebelum);
});

it('log aktivitas hanya dapat dilihat Super Admin dan tidak dapat diubah siapa pun', function () {
    foreach (['admin', 'petugas', 'warga'] as $peran) {
        expect(Skenario::user($peran)->can('view_any_activity'))->toBeFalse();
    }

    $sa = Skenario::user('superadmin');
    expect($sa->can('view_any_activity'))->toBeTrue();
    foreach (['create_activity', 'update_activity', 'delete_activity', 'delete_any_activity'] as $izin) {
        expect($sa->can($izin))->toBeFalse();
    }
});
