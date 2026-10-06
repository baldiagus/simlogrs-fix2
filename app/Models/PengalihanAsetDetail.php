<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengalihanAsetDetail extends BaseModel
{
    public $timestamps = false;

    protected $table = 'pengalihan_aset_detail';

    public function pengalihanAset(): BelongsTo
    {
        return $this->belongsTo(PengalihanAset::class, 'pengalihan_aset_id');
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }
}
