<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Peran extends BaseModel
{
    protected $table = 'peran';

    public function penggunaPeran(): HasMany
    {
        return $this->hasMany(PenggunaPeran::class, 'peran_id');
    }
}
