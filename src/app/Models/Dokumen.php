<?php

namespace App\Models;

use App\Enums\StatusVerifikasiDokumen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dokumen extends Model
{
    protected $table = 'dokumen';

    protected $fillable = [
        'permohonan_id', 'persyaratan_id', 'nama_file', 'lokasi_file',
        'tipe_file', 'ukuran_file', 'status_verifikasi', 'tanggal_upload',
    ];

    protected $casts = [
        'status_verifikasi' => StatusVerifikasiDokumen::class,
        'tanggal_upload' => 'datetime',
    ];

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class, 'permohonan_id');
    }

    public function persyaratan(): BelongsTo
    {
        return $this->belongsTo(Persyaratan::class, 'persyaratan_id');
    }
}
