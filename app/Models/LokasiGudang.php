<?php

namespace App\Models;

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
