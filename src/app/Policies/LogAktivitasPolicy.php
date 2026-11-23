<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Log aktivitas adalah jejak audit (FR-49, FR-51): hanya Super Admin yang boleh MELIHAT,
 * dan tidak seorang pun boleh membuat, mengubah, atau menghapusnya lewat aplikasi.
 * Berbasis role, bukan permission: startup kontainer memberi super_admin seluruh permission Shield,
 * jadi izin tidak boleh menjadi satu-satunya penjaga. Didaftarkan eksplisit di AppServiceProvider.
 */
class LogAktivitasPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, Activity $model): bool
    {
        return $user->hasRole('super_admin');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $model): bool
    {
        return false;
    }

    public function delete(User $user, Activity $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Activity $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Activity $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Activity $model): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
