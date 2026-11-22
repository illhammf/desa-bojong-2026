<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Petugas extends Model
{
    protected $table = 'petugas';
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = ['user_id', 'nip'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatus::class, 'petugas_id', 'user_id');
    }
}
