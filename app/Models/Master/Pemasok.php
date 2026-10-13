<?php

namespace App\Models\Master;

use App\Models\BaseModel;
use App\Models\Pengadaan\PesananPembelian;
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
