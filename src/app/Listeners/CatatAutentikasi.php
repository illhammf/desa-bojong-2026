<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Str;

/**
 * Mencatat login, logout, dan login gagal untuk semua peran (FR-51).
 * Laravel menemukan listener ini otomatis dari method handle* yang bertipe event.
 * Kata sandi tidak pernah dicatat.
 */
class CatatAutentikasi
{
    public function handleLogin(Login $event): void
    {
        activity('Autentikasi')
            ->causedBy($event->user)
            ->event('login')
            ->withProperties($this->konteks(['peran' => method_exists($event->user, 'getRoleNames') ? $event->user->getRoleNames()->first() : null]))
            ->log('Login berhasil');
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        activity('Autentikasi')
            ->causedBy($event->user)
            ->event('logout')
            ->withProperties($this->konteks([]))
            ->log('Logout');
    }

    public function handleFailed(Failed $event): void
    {
        activity('Autentikasi')
            ->event('login_gagal')
            ->withProperties($this->konteks(['email' => $event->credentials['email'] ?? null]))
            ->log('Login gagal');
    }

    private function konteks(array $tambahan): array
    {
        return $tambahan + [
            'ip' => request()->ip(),
            'perangkat' => Str::limit((string) request()->userAgent(), 120, ''),
        ];
    }
}
