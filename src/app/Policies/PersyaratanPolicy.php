<?php

namespace App\Policies;

use App\Models\Persyaratan;
use App\Models\User;

/** Persyaratan layanan hanya dikelola Administrator dan Super Admin (FR-30). */
class PersyaratanPolicy
{
    private function boleh(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function viewAny(User $user): bool
    {
        return $this->boleh($user);
    }

    public function view(User $user, Persyaratan $model): bool
    {
        return $this->boleh($user);
    }

    public function create(User $user): bool
    {
        return $this->boleh($user);
    }

    public function update(User $user, Persyaratan $model): bool
    {
        return $this->boleh($user);
    }

    public function delete(User $user, Persyaratan $model): bool
    {
        return $this->boleh($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $this->boleh($user);
    }

    public function forceDelete(User $user, Persyaratan $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Persyaratan $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Persyaratan $model): bool
    {
        return false;
    }
}
