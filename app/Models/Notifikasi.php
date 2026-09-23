<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notifikasi extends BaseModel
{
    const UPDATED_AT = null;

    protected $table = 'notifikasi';

    protected function casts(): array
    {
        return [
            'dibaca'      => 'boolean',
            'dibuat_pada' => 'datetime',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }
}
