<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends BaseModel
{
    protected $table = 'unit';

    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class, 'unit_id');
    }

    public function aset(): HasMany
    {
        return $this->hasMany(Aset::class, 'unit_id');
    }

    public function kepalaUnit(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepala_unit_id');
    }
}
