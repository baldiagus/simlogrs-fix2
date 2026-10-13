<?php

namespace App\Models\Distribusi;

use App\Models\BaseModel;
use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenerimaanDistribusiDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'penerimaan_distribusi_detail';

    protected function casts(): array
    {
        return [
            'jumlah_diterima' => 'integer',
        ];
    }

    public function penerimaanDistribusi(): BelongsTo
    {
        return $this->belongsTo(PenerimaanDistribusi::class, 'penerimaan_distribusi_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}
