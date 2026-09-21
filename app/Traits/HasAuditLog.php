<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait HasAuditLog
{
    use LogsActivity;

    /**
     * Konfigurasi perekaman audit otomatis Spatie ActivityLog.
     * Hanya mencatat atribut yang berubah (dirty) dan menyembunyikan kolom noise/sensitif.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly([
                'diperbarui_pada',
                'updated_at',
                'remember_token',
                'password',
            ]);
    }
}
