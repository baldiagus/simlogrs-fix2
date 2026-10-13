<?php

namespace App\Models\Auth;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Peran extends BaseModel
{
    protected $table = 'peran';

    public function penggunaPeran(): HasMany
    {
        return $this->hasMany(PenggunaPeran::class, 'peran_id');
    }
}
