<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SatuanBarang extends BaseModel
{
    protected $table = 'satuan_barang';

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'satuan_id');
    }
}
