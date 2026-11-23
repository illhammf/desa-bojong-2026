<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use LogsActivity;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory,HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'avatar_url',
        'name',
        'email',
        'no_hp',
        'status_akun',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            return asset('storage/' . $this->avatar_url);
        } else {
            $hash = md5(strtolower(trim($this->email)));

            return 'https://www.gravatar.com/avatar/' . $hash . '?d=mp&r=g&s=250';
        }
    }

    /** Profil tambahan per peran (SRS 6.3). Super Admin tidak punya tabel profil. */
    public function admin(): HasOne
    {
        return $this->hasOne(Admin::class, 'user_id');
    }

    public function petugas(): HasOne
    {
        return $this->hasOne(Petugas::class, 'user_id');
    }

    public function masyarakat(): HasOne
    {
        return $this->hasOne(Masyarakat::class, 'user_id');
    }

    public function isAktif(): bool
    {
        return $this->status_akun === 'aktif';
    }

    /** Panel hanya untuk pengelola yang aktif. Masyarakat ditolak (FR-32). */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAktif()
            && $this->hasAnyRole(['petugas', 'admin', 'super_admin']);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Akun')
            ->logOnly(['name', 'email', 'no_hp', 'status_akun', 'avatar_url'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e): string => match ($e) {
                'created' => 'Akun dibuat',
                'updated' => 'Akun diperbarui',
                'deleted' => 'Akun dihapus',
                default => $e,
            });
    }
}
