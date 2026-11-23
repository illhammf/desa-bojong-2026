<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DokumenController extends Controller
{
    /**
     * Membuka dokumen dari disk privat (PRD 7.16, FR-43).
     * Aturan akses = kebijakan akses permohonan: pemilik dan petugas saja.
     */
    public function tampil(Dokumen $dokumen)
    {
        $dokumen->loadMissing('permohonan');

        abort_unless(Gate::forUser(auth()->user())->allows('view', $dokumen->permohonan), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($dokumen->lokasi_file), 404);

        return $disk->response($dokumen->lokasi_file, $dokumen->nama_file, [
            'Content-Type' => $dokumen->tipe_file,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
