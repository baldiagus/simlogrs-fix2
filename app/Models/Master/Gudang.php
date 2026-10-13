<?php

namespace App\Models\Master;

use App\Models\BaseModel;
use App\Models\Persediaan\StokBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gudang extends BaseModel
{
    protected $table = 'gudang';

    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class, 'gudang_id');
    }

    public function lokasiGudang(): HasMany
    {
        return $this->hasMany(LokasiGudang::class, 'gudang_id');
    }

    public function stokBatch(): HasMany
    {
        return $this->hasMany(StokBatch::class, 'gudang_id');
    }

    public function kepalaGudang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepala_gudang_id');
    }
}
