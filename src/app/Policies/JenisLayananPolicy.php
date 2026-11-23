<?php

namespace App\Policies;

use App\Models\JenisLayanan;
use App\Models\User;

/**
 * Master data layanan hanya dikelola Administrator dan Super Admin (FR-29).
 * Petugas Desa tidak berwenang. Berbasis role agar tidak bisa dilonggarkan lewat UI.
 */
class JenisLayananPolicy
{
    private function boleh(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function viewAny(User $user): bool
    {
        return $this->boleh($user);
    }

    public function view(User $user, JenisLayanan $model): bool
    {
        return $this->boleh($user);
    }

    public function create(User $user): bool
    {
        return $this->boleh($user);
    }

    public function update(User $user, JenisLayanan $model): bool
    {
        return $this->boleh($user);
    }

    public function delete(User $user, JenisLayanan $model): bool
    {
        return $this->boleh($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, JenisLayanan $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, JenisLayanan $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, JenisLayanan $model): bool
    {
        return false;
    }
}
