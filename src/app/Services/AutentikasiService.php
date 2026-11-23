<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login dan logout satu halaman untuk semua peran (BRD FR-02, FR-03, FR-32, NFR-01).
 * - Akun nonaktif, email tak dikenal, dan kata sandi salah mendapat PESAN YANG SAMA (tidak membocorkan status akun).
 * - Percobaan dibatasi per kombinasi email dan IP.
 * - Pencatatan login/logout/gagal dilakukan listener App\Listeners\CatatAutentikasi (FR-51).
 */
class AutentikasiService
{
    public const MAKS_PERCOBAAN = 5;

    public const JEDA_DETIK = 60;

    public const PESAN_GAGAL = 'Email atau kata sandi tidak sesuai.';

    public const TUJUAN_PANEL = '/admin';

    public const TUJUAN_MASYARAKAT = '/beranda';

    /** @throws ValidationException */
    public function masuk(string $email, string $kataSandi, bool $ingat = false, ?string $ip = null): User
    {
        $email = Str::lower(trim($email));

        $galat = [];
        if ($email === '') {
            $galat['email'] = 'Email wajib diisi.';
        }
        if ($kataSandi === '') {
            $galat['password'] = 'Kata sandi wajib diisi.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        $kunci = 'masuk:' . sha1($email . '|' . ($ip ?? request()->ip() ?? 'tidak-diketahui'));

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            $detik = RateLimiter::availableIn($kunci);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
            ]);
        }

        $guard = Auth::guard('web');

        // status_akun masuk ke syarat pencarian: akun nonaktif diperlakukan seperti tidak ada.
        $berhasil = $guard->attempt([
            'email' => $email,
            'password' => $kataSandi,
            'status_akun' => 'aktif',
        ], $ingat);

        if (! $berhasil) {
            RateLimiter::hit($kunci, self::JEDA_DETIK);

            throw ValidationException::withMessages(['email' => self::PESAN_GAGAL]);
        }

        /** @var User $user */
        $user = $guard->user();

        // Akun tanpa peran yang dikenal tidak boleh masuk.
        if (! $user->hasAnyRole(array_keys(PenggunaService::LABEL))) {
            $guard->logout();
            RateLimiter::hit($kunci, self::JEDA_DETIK);

            throw ValidationException::withMessages(['email' => self::PESAN_GAGAL]);
        }

        RateLimiter::clear($kunci);

        return $user;
    }

    /** Halaman tujuan setelah masuk (FR-32). */
    public function tujuan(User $user): string
    {
        return $user->hasRole('masyarakat') ? self::TUJUAN_MASYARAKAT : self::TUJUAN_PANEL;
    }

    public function keluar(): void
    {
        Auth::guard('web')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }
}
