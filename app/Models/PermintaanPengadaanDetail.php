<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermintaanPengadaanDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'permintaan_pengadaan_detail';

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'jumlah_disetujui' => 'integer',
        ];
    }

    public function permintaanPengadaan(): BelongsTo
    {
        return $this->belongsTo(PermintaanPengadaan::class, 'permintaan_pengadaan_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
