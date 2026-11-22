<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use Illuminate\Support\Facades\Storage;

class DokumenController extends Controller
{
    /**
     * Membuka dokumen dari disk privat (PRD 7.16, FR-43).
     * Boleh: pemilik permohonan (masyarakat) dan petugas. Admin dan Super Admin tidak.
     */
    public function tampil(Dokumen $dokumen)
    {
        $user = auth()->user();
        $dokumen->loadMissing('permohonan');

        $boleh = $user->hasRole('petugas')
            || ($user->hasRole('masyarakat') && $dokumen->permohonan->user_id === $user->id);

        abort_unless($boleh, 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($dokumen->lokasi_file), 404);

        return $disk->response($dokumen->lokasi_file, $dokumen->nama_file, [
            'Content-Type' => $dokumen->tipe_file,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
