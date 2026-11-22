<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Masyarakat extends Model
{
    protected $table = 'masyarakat';
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = ['user_id', 'nik', 'alamat'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function permohonan(): HasMany
    {
        return $this->hasMany(Permohonan::class, 'user_id', 'user_id');
    }
}
