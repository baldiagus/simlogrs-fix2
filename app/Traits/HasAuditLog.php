<?php

namespace App\Traits;

use App\Models\LogAudit;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait HasAuditLog
{
    use LogsActivity;

    /**
     * Konfigurasi kompatibilitas Spatie ActivityLog.
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

    /**
     * Boot trait untuk otomatis merekam snapshot diff ke tabel log_audit (JSONB).
     */
    public static function bootHasAuditLog(): void
    {
        static::created(function ($model) {
            $after = $model->cleanAuditAttributes($model->getAttributes());
            $model->writeLogAudit('TAMBAH', null, $after);
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            $ignored = ['diperbarui_pada', 'updated_at', 'remember_token', 'password'];
            $changes = array_diff_key($dirty, array_flip($ignored));

            if (! empty($changes)) {
                $before = array_intersect_key($model->getOriginal(), $changes);
                $after = $changes;
                $model->writeLogAudit('UBAH', $before, $after);
            }
        });

        static::deleted(function ($model) {
            $before = $model->cleanAuditAttributes($model->getOriginal());
            $model->writeLogAudit('HAPUS', $before, null);
        });
    }

    /**
     * Tulis rekaman ke tabel log_audit secara aman.
     */
    public function writeLogAudit(string $aksi, ?array $before, ?array $after): void
    {
        try {
            $user = auth()->user();
            $request = request();

            LogAudit::create([
                'pengguna_id'     => $user?->id,
                'peran_id'        => $user?->roles?->first()?->id,
                'referensi_jenis' => class_basename($this),
                'referensi_id'    => $this->getKey(),
                'aksi'            => $aksi,
                'data_sebelum'    => $before,
                'data_sesudah'    => $after,
                'ip_address'      => $request ? $request->ip() : '127.0.0.1',
                'device'          => $request ? substr($request->userAgent() ?? 'CLI / System', 0, 255) : 'CLI / System',
                'dibuat_pada'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Gagal mencatat log_audit untuk " . class_basename($this) . ": " . $e->getMessage());
        }
    }

    /**
     * Bersihkan atribut dari kolom sensitif / noise.
     */
    protected function cleanAuditAttributes(array $attrs): array
    {
        $ignored = ['password', 'remember_token', 'diperbarui_pada', 'updated_at'];
        return array_diff_key($attrs, array_flip($ignored));
    }
}
