<?php

namespace App\Models;

use App\Enums\StatusPermohonan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permohonan extends Model
{
    protected $table = 'permohonan';

    protected $fillable = [
        'user_id', 'jenis_layanan_id', 'nomor_permohonan', 'tahun', 'urut',
        'tanggal_pengajuan', 'alasan_pengajuan', 'status', 'catatan_petugas',
    ];

    protected $casts = [
        'status' => StatusPermohonan::class,
        'tanggal_pengajuan' => 'datetime',
    ];

    public function masyarakat(): BelongsTo
    {
        return $this->belongsTo(Masyarakat::class, 'user_id', 'user_id');
    }

    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function layanan(): BelongsTo
    {
        return $this->belongsTo(JenisLayanan::class, 'jenis_layanan_id');
    }

    public function isian(): HasMany
    {
        return $this->hasMany(IsianPermohonan::class, 'permohonan_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(Dokumen::class, 'permohonan_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatStatus::class, 'permohonan_id')->orderBy('waktu_perubahan')->orderBy('id');
    }
}
