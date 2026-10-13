<?php

namespace App\Models\Audit;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class LogAudit extends BaseModel
{
    const UPDATED_AT = null;

    protected $table = 'log_audit';

    protected function casts(): array
    {
        return [
            'referensi_id' => 'integer',
            'data_sebelum' => 'array',
            'data_sesudah' => 'array',
            'dibuat_pada'  => 'datetime',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    public function peran(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'peran_id');
    }
}
