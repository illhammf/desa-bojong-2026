<?php

namespace App\Models;

use App\Enums\StatusPermohonan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permohonan extends Model
{
    /** Hanya AlurStatusService yang boleh mengubah status (NFR-29). */
    private static bool $izinUbahStatus = false;

    public static function denganIzinUbahStatus(callable $aksi): mixed
    {
        $lama = static::$izinUbahStatus;
        static::$izinUbahStatus = true;

        try {
            return $aksi();
        } finally {
            static::$izinUbahStatus = $lama;
        }
    }

    protected static function booted(): void
    {
        static::updating(function (Permohonan $p) {
            if ($p->isDirty('status') && ! static::$izinUbahStatus) {
                throw new \LogicException('Status permohonan hanya dapat diubah melalui AlurStatusService.');
            }
        });
    }

    protected $table = 'permohonan';

    protected $fillable = [
        'user_id', 'jenis_layanan_id', 'nomor_permohonan', 'tahun', 'urut',
        'tanggal_pengajuan', 'alasan_pengajuan', 'status', 'catatan_petugas',
    ];

    protected $casts = [
        'status' => StatusPermohonan::class,
        'tanggal_pengajuan' => 'datetime',
    ];

    /** Permohonan milik pengguna tertentu (FR-11, BR-02). */
    public function scopeMilik(Builder $query, User $user): Builder
    {
        return $query->where('permohonan.user_id', $user->id);
    }

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
