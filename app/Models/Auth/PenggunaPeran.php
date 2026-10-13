<?php

namespace App\Models\Auth;

use App\Models\BaseModel;
use App\Models\Master\Gudang;
use App\Models\Master\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenggunaPeran extends BaseModel
{
    protected $table = 'pengguna_peran';

    const UPDATED_AT = null;

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    public function peran(): BelongsTo
    {
        return $this->belongsTo(Peran::class, 'peran_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }
}
