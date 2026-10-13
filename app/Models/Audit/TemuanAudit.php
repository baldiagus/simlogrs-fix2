<?php

namespace App\Models\Audit;

use App\Models\BaseModel;
use App\Models\Master\Gudang;
use App\Models\Master\Unit;
use App\Models\User;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemuanAudit extends BaseModel
{
    use HasAuditLog;

    protected $table = 'temuan_audit';

    protected function casts(): array
    {
        return [
            'tanggal_audit'   => 'date',
            'due_date_capa'   => 'date',
            'bukti_perbaikan' => 'array',
            'dibuat_pada'     => 'datetime',
            'diperbarui_pada' => 'datetime',
        ];
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dievaluasi_oleh');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }
}
