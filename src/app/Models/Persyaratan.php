<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Enums\TipePersyaratan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persyaratan extends Model
{
    use LogsActivity;

    protected $table = 'persyaratan';

    protected $fillable = [
        'jenis_layanan_id', 'nama_persyaratan', 'tipe_persyaratan', 'tipe_input',
        'opsi', 'wajib', 'keterangan', 'urutan', 'aktif',
    ];

    protected $casts = [
        'tipe_persyaratan' => TipePersyaratan::class,
        'opsi' => 'array',
        'wajib' => 'boolean',
        'aktif' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Persyaratan baru otomatis diletakkan di urutan paling bawah pada layanannya.
        static::creating(function (Persyaratan $p) {
            if (empty($p->urutan)) {
                $p->urutan = (int) static::where('jenis_layanan_id', $p->jenis_layanan_id)->max('urutan') + 1;
            }
        });
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeIsian(Builder $query): Builder
    {
        return $query->where('tipe_persyaratan', TipePersyaratan::Isian->value);
    }

    public function scopeDokumen(Builder $query): Builder
    {
        return $query->where('tipe_persyaratan', TipePersyaratan::Dokumen->value);
    }

    public function layanan(): BelongsTo
    {
        return $this->belongsTo(JenisLayanan::class, 'jenis_layanan_id');
    }

    public function isianPermohonan(): HasMany
    {
        return $this->hasMany(IsianPermohonan::class, 'persyaratan_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(Dokumen::class, 'persyaratan_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Persyaratan')
            ->logFillable()
            ->logExcept(['urutan'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e): string => match ($e) {
                'created' => 'Persyaratan dibuat',
                'updated' => 'Persyaratan diperbarui',
                'deleted' => 'Persyaratan dihapus',
                default => $e,
            });
    }
}
