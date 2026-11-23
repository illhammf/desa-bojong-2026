<?php

namespace App\Policies;

use App\Models\Permohonan;
use App\Models\User;

/**
 * Akses permohonan (BRD FR-11, FR-43, BR-02, BR-05, BR-33).
 * - Masyarakat: hanya permohonan miliknya sendiri.
 * - Petugas: seluruh permohonan.
 * - Administrator dan Super Admin: tidak ada (mereka hanya memantau lewat dashboard dan laporan).
 * Perubahan data hanya lewat PengajuanService dan AlurStatusService, jadi update dan delete selalu ditolak.
 * Didaftarkan eksplisit di AppServiceProvider (bukan "PermohonanPolicy": nama itu dipakai generator Shield).
 */
class AksesPermohonanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAktif() && $user->hasAnyRole(['masyarakat', 'petugas']);
    }

    public function view(User $user, Permohonan $permohonan): bool
    {
        if (! $user->isAktif()) {
            return false;
        }

        if ($user->hasRole('petugas')) {
            return true;
        }

        return $user->hasRole('masyarakat') && $permohonan->user_id === $user->id;
    }

    /** Membuka formulir pengajuan (BR-01). Penyimpanannya tetap lewat PengajuanService. */
    public function create(User $user): bool
    {
        return $user->isAktif() && $user->hasRole('masyarakat');
    }

    public function update(User $user, Permohonan $permohonan): bool
    {
        return false;
    }

    public function delete(User $user, Permohonan $permohonan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
