<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisLayanan extends Model
{
    protected $table = 'jenis_layanan';

    protected $fillable = ['kode', 'nama_layanan', 'deskripsi', 'estimasi_waktu', 'aktif'];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function persyaratan(): HasMany
    {
        return $this->hasMany(Persyaratan::class, 'jenis_layanan_id')->orderBy('urutan');
    }

    public function permohonan(): HasMany
    {
        return $this->hasMany(Permohonan::class, 'jenis_layanan_id');
    }
}
