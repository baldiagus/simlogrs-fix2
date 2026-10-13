<?php

namespace App\Models\Master;

use App\Models\Aset\Aset;
use App\Models\BaseModel;
use App\Models\Persediaan\StokBatch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LokasiGudang extends BaseModel
{
    protected $table = 'lokasi_gudang';

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function aset(): HasMany
    {
        return $this->hasMany(Aset::class, 'lokasi_gudang_id');
    }

    public function stokBatch(): HasMany
    {
        return $this->hasMany(StokBatch::class, 'lokasi_gudang_id');
    }
}
