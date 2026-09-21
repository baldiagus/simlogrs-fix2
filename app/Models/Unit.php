<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends BaseModel
{
    protected $table = 'unit';

    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class, 'unit_id');
    }
}
