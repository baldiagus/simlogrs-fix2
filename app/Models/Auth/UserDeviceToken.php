<?php

namespace App\Models\Auth;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token FCM perangkat milik pengguna (satu pengguna bisa punya banyak perangkat).
 */
class UserDeviceToken extends BaseModel
{
    const UPDATED_AT = null;

    protected $table = 'user_device_tokens';

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'dibuat_pada'  => 'datetime',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }
}
