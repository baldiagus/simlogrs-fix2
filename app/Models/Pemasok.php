<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Pemasok extends BaseModel
{
    protected $table = 'pemasok';

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'pemasok_default_id');
    }
}
