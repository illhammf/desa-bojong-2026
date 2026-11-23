<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Masyarakat;
use App\Models\Permohonan;
use App\Models\Petugas;
use App\Models\RiwayatStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Aturan pengelolaan akun (BRD: FR-27, FR-28, FR-46, FR-48, FR-50, BR-30, BR-31, BR-32).
 * Aturan ini tidak bergantung pada permission Shield, jadi tidak bisa dilonggarkan lewat UI.
 */
class PenggunaService
{
    public const LABEL = [
        'masyarakat' => 'Masyarakat',
        'petugas' => 'Petugas Desa',
        'admin' => 'Administrator',
        'super_admin' => 'Super Admin',
    ];

    /** BR-30: Administrator hanya boleh menetapkan peran masyarakat dan petugas. */
    public static function peranYangBolehDipilih(User $aktor): array
    {
        return $aktor->hasRole('super_admin') ? array_keys(self::LABEL) : ['masyarakat', 'petugas'];
    }

    public static function superAdminAktifSelain(User $target): int
    {
        return User::role('super_admin')
            ->where('status_akun', 'aktif')
            ->where('id', '!=', $target->id)
            ->count();
    }

    /** Pesan kesalahan bila simpan akun melanggar aturan, atau null bila aman. */
    public static function periksa(User $aktor, ?User $target, array $data): ?string
    {
        $role = $data['role'] ?? null;
        $status = $data['status_akun'] ?? 'aktif';

        if (! array_key_exists((string) $role, self::LABEL)) {
            return 'Peran tidak valid.';
        }

        $berubah = ! $target || ! $target->hasRole($role);

        if ($berubah && ! in_array($role, self::peranYangBolehDipilih($aktor), true)) {
            return 'Anda tidak berwenang menetapkan peran ini.';
        }

        if ($target) {
            if ($target->id === $aktor->id) {
                if ($berubah) {
                    return 'Anda tidak dapat mengubah peran akun sendiri.';
                }
                if ($status !== 'aktif') {
                    return 'Anda tidak dapat menonaktifkan akun sendiri.';
                }
            }

            if ($berubah && Permohonan::where('user_id', $target->id)->exists()) {
                return 'Peran tidak dapat diubah karena akun ini sudah memiliki permohonan.';
            }

            $mencabutSuperAdmin = $target->hasRole('super_admin') && ($berubah || $status !== 'aktif');
            if ($mencabutSuperAdmin && self::superAdminAktifSelain($target) < 1) {
                return 'Harus ada minimal satu Super Admin aktif.';
            }
        }

        return null;
    }

    public static function alasanTidakBolehNonaktif(User $aktor, User $target): ?string
    {
        if ($target->id === $aktor->id) {
            return 'Anda tidak dapat menonaktifkan akun sendiri.';
        }

        if ($target->hasRole('super_admin') && self::superAdminAktifSelain($target) < 1) {
            return 'Harus ada minimal satu Super Admin aktif.';
        }

        return null;
    }

    public static function alasanTidakBolehDihapus(User $aktor, User $target): ?string
    {
        if ($target->id === $aktor->id) {
            return 'Anda tidak dapat menghapus akun sendiri.';
        }

        if ($target->hasRole('super_admin') && self::superAdminAktifSelain($target) < 1) {
            return 'Harus ada minimal satu Super Admin aktif.';
        }

        if (Permohonan::where('user_id', $target->id)->exists()) {
            return 'Akun ini sudah memiliki permohonan. Nonaktifkan saja agar riwayat tetap utuh.';
        }

        if (RiwayatStatus::where('petugas_id', $target->id)->exists()) {
            return 'Akun ini pernah memproses permohonan. Nonaktifkan saja agar riwayat tetap utuh.';
        }

        return null;
    }

    /** Menetapkan role dan menyelaraskan tabel profil (admin, petugas, masyarakat). */
    public function terapkan(User $user, array $data): void
    {
        $role = $data['role'];

        DB::transaction(function () use ($user, $role, $data) {
            $sebelum = $user->roles()->pluck('name')->first();
            $user->syncRoles([$role]);

            if ($sebelum !== $role) {
                activity('Akun')
                    ->performedOn($user)
                    ->event('peran')
                    ->withProperties(['dari' => $sebelum, 'ke' => $role])
                    ->log($sebelum
                        ? "Peran akun {$user->email} diubah dari {$sebelum} ke {$role}"
                        : "Peran akun {$user->email} ditetapkan: {$role}");
            }

            if ($role === 'masyarakat') {
                Masyarakat::updateOrCreate(
                    ['user_id' => $user->id],
                    ['nik' => $data['nik'] ?? null, 'alamat' => $data['alamat'] ?? null]
                );
            } else {
                Masyarakat::where('user_id', $user->id)->delete();
            }

            if ($role === 'petugas') {
                Petugas::updateOrCreate(['user_id' => $user->id], ['nip' => $data['nip'] ?? null]);
            } else {
                Petugas::where('user_id', $user->id)->delete();
            }

            if ($role === 'admin') {
                Admin::updateOrCreate(['user_id' => $user->id], ['nip' => $data['nip'] ?? null]);
            } else {
                Admin::where('user_id', $user->id)->delete();
            }
        });
    }
}
