<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriBarang extends BaseModel
{
    protected $table = 'kategori_barang';

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'kategori_id');
    }
}
