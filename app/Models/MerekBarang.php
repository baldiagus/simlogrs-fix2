<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MerekBarang extends BaseModel
{
    protected $table = 'merek_barang';

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'merek_id');
    }
}
