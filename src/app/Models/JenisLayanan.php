<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisLayanan extends Model
{
    use LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Layanan')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e): string => match ($e) {
                'created' => 'Layanan dibuat',
                'updated' => 'Layanan diperbarui',
                'deleted' => 'Layanan dihapus',
                default => $e,
            });
    }
}
