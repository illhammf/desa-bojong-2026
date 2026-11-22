<?php

namespace App\Models;

use App\Enums\StatusPermohonan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatStatus extends Model
{
    protected $table = 'riwayat_status';

    public $timestamps = false;

    protected $fillable = ['permohonan_id', 'petugas_id', 'status', 'keterangan', 'waktu_perubahan'];

    protected $casts = [
        'status' => StatusPermohonan::class,
        'waktu_perubahan' => 'datetime',
    ];

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class, 'permohonan_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Petugas::class, 'petugas_id', 'user_id');
    }
}
