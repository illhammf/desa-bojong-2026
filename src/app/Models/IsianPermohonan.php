<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IsianPermohonan extends Model
{
    protected $table = 'isian_permohonan';

    protected $fillable = ['permohonan_id', 'persyaratan_id', 'nilai_isian'];

    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class, 'permohonan_id');
    }

    public function persyaratan(): BelongsTo
    {
        return $this->belongsTo(Persyaratan::class, 'persyaratan_id');
    }
}
