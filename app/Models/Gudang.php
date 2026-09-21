<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Gudang extends BaseModel
{
    protected $table = 'gudang';

    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class, 'gudang_id');
    }
}
