<?php

namespace App\Models;

use App\Models\PesananPembelian;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pemasok extends BaseModel
{
    protected $table = 'pemasok';

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'pemasok_default_id');
    }

    public function pesananPembelian(): HasMany
    {
        return $this->hasMany(PesananPembelian::class, 'pemasok_id');
    }
}
