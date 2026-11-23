<?php

use App\Enums\StatusPermohonan as S;
use App\Models\JenisLayanan;
use App\Models\Permohonan;
use App\Models\Persyaratan;
use App\Models\User;
use App\Services\AlurStatusService;
use App\Services\PenggunaService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/*
 * Uji pencatatan aktivitas (FR-51). Semua perubahan dibatalkan (rollback).
 * Jalankan: dca tinker --execute="require base_path('database/uji_audit.php');"
 */

$lulus = 0;
$gagal = 0;
$cek = function (string $nama, callable $uji) use (&$lulus, &$gagal): void {
    try {
        $hasil = $uji();
    } catch (Throwable $e) {
        $hasil = get_class($e) . ': ' . $e->getMessage();
    }

    if ($hasil === true) {
        $lulus++;
        echo "  [OK]    {$nama}\n";
    } else {
        $gagal++;
        echo "  [GAGAL] {$nama} => " . (is_string($hasil) ? $hasil : var_export($hasil, true)) . "\n";
    }
};

$terakhir = fn (string $log, string $event) => Activity::where('log_name', $log)->where('event', $event)->orderByDesc('id')->first();

DB::beginTransaction();

try {
    $sa = User::where('email', 'superadmin@demo.test')->firstOrFail();
    $admin = User::where('email', 'admin@demo.test')->firstOrFail();
    $petugas = User::where('email', 'petugas@demo.test')->firstOrFail();
    $warga = User::where('email', 'warga@demo.test')->firstOrFail();
    $alur = app(AlurStatusService::class);

    echo "\nUJI PENCATATAN AKTIVITAS\n";

    $cek('1. Login tercatat (Autentikasi/login) dengan pelaku dan IP', function () use ($terakhir, $warga) {
        event(new Login('web', $warga, false));
        $a = $terakhir('Autentikasi', 'login');

        if (! $a) {
            return 'tidak ada log (listener tidak terdeteksi?)';
        }
        if ((int) $a->causer_id !== $warga->id) {
            return 'pelaku salah';
        }

        return array_key_exists('ip', $a->properties->toArray()) ?: 'properti ip tidak ada';
    });

    $cek('2. Login gagal tercatat tanpa kata sandi', function () use ($terakhir) {
        event(new Failed('web', null, ['email' => 'penyusup@uji.test', 'password' => 'RAHASIA123']));
        $a = $terakhir('Autentikasi', 'login_gagal');

        if (! $a) {
            return 'tidak ada log';
        }
        if (($a->properties['email'] ?? null) !== 'penyusup@uji.test') {
            return 'email percobaan tidak tercatat';
        }

        return ! str_contains($a->properties->toJson(), 'RAHASIA123') ?: 'KATA SANDI BOCOR ke log';
    });

    $cek('3. Logout tercatat', function () use ($terakhir, $warga) {
        event(new Logout('web', $warga));
        $a = $terakhir('Autentikasi', 'logout');

        return ($a && (int) $a->causer_id === $warga->id) ?: 'tidak ada log atau pelaku salah';
    });

    $cek('4. Perubahan layanan tercatat dengan nilai lama dan baru', function () use ($terakhir) {
        $l = JenisLayanan::where('kode', 'UMM')->firstOrFail();
        $l->update(['estimasi_waktu' => '2 Hari Kerja']);
        $a = $terakhir('Layanan', 'updated');

        if (! $a || (int) $a->subject_id !== $l->id) {
            return 'tidak ada log untuk layanan';
        }
        $p = $a->properties->toArray();
        if (($p['attributes']['estimasi_waktu'] ?? null) !== '2 Hari Kerja') {
            return 'nilai baru tidak tercatat';
        }

        return array_key_exists('estimasi_waktu', $p['old'] ?? []) ?: 'nilai lama tidak tercatat';
    });

    $cek('5. Perubahan persyaratan tercatat (termasuk enum); ubah urutan saja tidak mencatat', function () use ($terakhir) {
        $p = Persyaratan::where('nama_persyaratan', 'KTP')->orderBy('id')->firstOrFail();
        $p->update(['wajib' => false, 'tipe_persyaratan' => 'isian']);
        $a = $terakhir('Persyaratan', 'updated');

        if (! $a || (int) $a->subject_id !== $p->id) {
            return 'tidak ada log untuk persyaratan';
        }
        $attr = $a->properties->toArray()['attributes'] ?? [];
        if (! array_key_exists('wajib', $attr) || ! array_key_exists('tipe_persyaratan', $attr)) {
            return 'atribut tidak tercatat: ' . implode(',', array_keys($attr));
        }

        $sebelum = Activity::count();
        $p->update(['urutan' => 99]);

        return Activity::count() === $sebelum ?: 'perubahan urutan ikut mencatat log';
    });

    $akun = null;
    $cek('6. Akun baru tercatat tanpa kata sandi; ubah kata sandi dan remember_token tidak mencatat; ubah no HP mencatat', function () use ($terakhir, &$akun) {
        $akun = User::create(['name' => 'Warga Audit', 'email' => 'audit@uji.test', 'no_hp' => '081200000055', 'status_akun' => 'aktif', 'password' => 'rahasia-audit']);
        $a = $terakhir('Akun', 'created');

        if (! $a || (int) $a->subject_id !== $akun->id) {
            return 'pembuatan akun tidak tercatat';
        }

        $sebelum = Activity::count();
        $akun->update(['password' => 'rahasia-baru-123']);
        $akun->forceFill(['remember_token' => Str::random(10)])->save();
        if (Activity::count() !== $sebelum) {
            return 'ubah kata sandi atau remember_token ikut mencatat log';
        }

        $akun->update(['no_hp' => '081200000066']);
        if (Activity::count() !== $sebelum + 1) {
            return 'ubah no HP tidak tercatat';
        }

        $bocor = Activity::where('log_name', 'Akun')->get()->contains(fn ($x) => str_contains($x->properties->toJson(), 'password'));

        return ! $bocor ?: 'kata sandi atau hash muncul di log akun';
    });

    $cek('7. Perubahan peran tercatat dengan pelaku super admin', function () use ($terakhir, $sa, &$akun) {
        if (! $akun) {
            return 'prasyarat (uji 6) gagal';
        }

        $akun->assignRole('masyarakat');
        Auth::setUser($sa);
        app(PenggunaService::class)->terapkan($akun->refresh(), ['role' => 'petugas', 'nip' => '1234']);

        $a = $terakhir('Akun', 'peran');
        if (! $a) {
            return 'tidak ada log peran';
        }
        if ((int) $a->causer_id !== $sa->id) {
            return 'pelaku bukan super admin';
        }
        $p = $a->properties->toArray();

        return (($p['dari'] ?? null) === 'masyarakat' && ($p['ke'] ?? null) === 'petugas') ?: 'dari/ke salah: ' . json_encode($p);
    });

    $permohonan = Permohonan::where('nomor_permohonan', 'like', 'REG-BMN-%')->firstOrFail();

    $cek('8. Perubahan status oleh petugas tercatat dengan dari/ke dan pelaku', function () use ($terakhir, $alur, $petugas, $permohonan) {
        $alur->ubah($permohonan->refresh(), S::PerluPerbaikan, $petugas, 'Foto KK buram, mohon unggah ulang.');
        $a = $terakhir('Permohonan', 'status');

        if (! $a || (int) $a->causer_id !== $petugas->id) {
            return 'tidak ada log atau pelaku salah';
        }
        $p = $a->properties->toArray();

        return (($p['dari'] ?? null) === 'dalam_pemeriksaan' && ($p['ke'] ?? null) === 'perlu_perbaikan') ?: 'dari/ke salah: ' . json_encode($p);
    });

    $cek('9. Perbaikan oleh sistem dicatat dengan pelaku pemohon', function () use ($terakhir, $alur, $permohonan) {
        $alur->ubah($permohonan->refresh(), S::DalamPemeriksaan, null, 'Pemohon mengirim perbaikan.');
        $a = $terakhir('Permohonan', 'status');

        return ($a && (int) $a->causer_id === (int) $permohonan->user_id) ?: 'pelaku bukan pemohon';
    });

    $cek('10. Perubahan status yang ditolak tidak meninggalkan log', function () use ($alur, $petugas, $permohonan) {
        $sebelum = Activity::count();

        try {
            $alur->ubah($permohonan->refresh(), S::SiapDiambil, $petugas);
        } catch (Throwable) {
        }

        return Activity::count() === $sebelum ?: 'log tertinggal setelah penolakan';
    });

    $cek('11. Pengajuan baru tercatat dengan pelaku pemohon', function () use ($terakhir, $alur) {
        $p = Permohonan::where('nomor_permohonan', 'like', 'REG-DOMU-%')->firstOrFail();
        $alur->catatAwal($p);
        $a = $terakhir('Permohonan', 'diajukan');

        return ($a && (int) $a->causer_id === (int) $p->user_id) ?: 'tidak ada log atau pelaku salah';
    });

    $cek('12. Log aktivitas hanya dapat dilihat Super Admin', function () use ($admin, $sa, $petugas, $warga) {
        if ($admin->can('view_any_activity') || $petugas->can('view_any_activity') || $warga->can('view_any_activity')) {
            return 'peran selain super admin dapat melihat log';
        }

        return $sa->can('view_any_activity') ?: 'super admin tidak dapat melihat log';
    });
} finally {
    Auth::forgetGuards();
    DB::rollBack();
}

echo "\nHasil: {$lulus} lulus, {$gagal} gagal.\n";
echo 'Rollback: log aktivitas ' . Activity::count() . " baris tersisa\n";
