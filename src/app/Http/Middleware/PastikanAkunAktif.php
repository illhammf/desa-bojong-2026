<?php

namespace App\Http\Middleware;

use App\Services\AutentikasiService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Akun yang dinonaktifkan di tengah sesi langsung dikeluarkan (FR-02). */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isAktif()) {
            app(AutentikasiService::class)->keluar();

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda tidak aktif. Hubungi petugas desa.']);
        }

        return $next($request);
    }
}
