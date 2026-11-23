<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Kebijakan akun (BR-30, BR-31). Didaftarkan eksplisit di AppServiceProvider.
 * Sengaja bukan "UserPolicy": nama itu dipakai generator Shield, yang bisa menimpa berkasnya
 * dengan versi bawaan yang tidak memeriksa record target.
 */
class AkunPolicy
{
    use HandlesAuthorization;

    /**
     * BR-30: Administrator tidak dapat melihat, mengubah, atau menghapus akun Administrator lain
     * maupun Super Admin. Super Admin dapat mengelola semua akun.
     */
    private function terjangkau(User $aktor, User $target): bool
    {
        if ($aktor->hasRole('super_admin') || $aktor->id === $target->id) {
            return true;
        }

        return ! $target->hasAnyRole(['admin', 'super_admin']);
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_user');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('view_user') && $this->terjangkau($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('create_user');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('update_user') && $this->terjangkau($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('delete_user')
            && $this->terjangkau($user, $model)
            && $user->id !== $model->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_user');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, User $model): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
