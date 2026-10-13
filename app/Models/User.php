<?php

namespace App\Models;

use App\Models\Auth\PenggunaPeran;
use App\Models\Master\Gudang;
use App\Models\Master\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable;

    protected $table = 'pengguna';

    const CREATED_AT = 'dibuat_pada';
    const UPDATED_AT = 'diperbarui_pada';

    protected $fillable = [
        'unit_id',
        'gudang_id',
        'nama',
        'nip',
        'username',
        'email',
        'password',
        'no_telepon',
        'status',
        'wajib_ganti_password',
        'terakhir_login',
        'ip_terakhir',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::updated(function (User $user) {
            if ($user->wasChanged('status') && $user->status === 'NONAKTIF') {
                $user->tokens()->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'wajib_ganti_password' => 'boolean',
            'terakhir_login' => 'datetime',
            'dibuat_pada' => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function penggunaPeran(): HasMany
    {
        return $this->hasMany(PenggunaPeran::class, 'pengguna_id');
    }
}
