<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermintaanDistribusiDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'permintaan_distribusi_detail';

    protected function casts(): array
    {
        return [
            'jumlah'           => 'integer',
            'jumlah_disetujui' => 'integer',
        ];
    }

    public function permintaanDistribusi(): BelongsTo
    {
        return $this->belongsTo(PermintaanDistribusi::class, 'permintaan_distribusi_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
