<?php

namespace App\Observers;

use App\Enums\Audit\StatusCapa as S;
use App\Models\Audit\TemuanAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemicu notifikasi CAPA: temuan baru (CAPA_BARU) ke auditee, tindak lanjut (CAPA_TINDAK_LANJUT) ke auditor,
 * hasil evaluasi (CAPA_DIEVALUASI) ke auditee. Kolom pemicu adalah `status_capa`, bukan `status`.
 */
class TemuanAuditObserver extends ObserverNotifikasi
{
    private const TAUTAN_AUDITEE = '/kepala-unit/temuan-capa';
    private const TAUTAN_AUDITOR = '/auditor/temuan-capa';

    /** Dasar hanya memicu pada kolom `status`; CAPA memakai `status_capa`. */
    public function updated(Model $model): void
    {
        if ($model->wasChanged('status_capa')) {
            $this->aman($model, false);
        }
    }

    protected function tangani(Model $model, bool $baru): void
    {
        /** @var TemuanAudit $t */
        $t = $model;

        if ($baru) {
            $this->kirim($this->auditee($t), 'Temuan Audit Baru', "Temuan {$t->kode} pada {$t->objek_audit} memerlukan tindakan korektif (CAPA) dari Anda.", 'CAPA_BARU', $t, self::TAUTAN_AUDITEE);

            return;
        }

        $lama = $t->getOriginal('status_capa');

        if ($t->status_capa === S::InReview->value) {
            $this->kirim($this->penerima()->pengguna($t->auditor_id), 'Tindak Lanjut CAPA Menunggu Evaluasi', "Tindak lanjut temuan {$t->kode} pada {$t->objek_audit} menunggu evaluasi Anda.", 'CAPA_TINDAK_LANJUT', $t, self::TAUTAN_AUDITOR);
        } elseif ($lama === S::InReview->value && $t->status_capa === S::Closed->value) {
            $this->kirim($this->auditee($t), 'CAPA Disetujui dan Ditutup', "Tindak lanjut temuan {$t->kode} pada {$t->objek_audit} telah dievaluasi dan ditutup.", 'CAPA_DIEVALUASI', $t, self::TAUTAN_AUDITEE);
        } elseif ($lama === S::InReview->value && $t->status_capa === S::InProgress->value) {
            $this->kirim($this->auditee($t), 'CAPA Dikembalikan untuk Perbaikan', "Tindak lanjut temuan {$t->kode} pada {$t->objek_audit} dikembalikan auditor. Mohon perbaiki dan kirim ulang.", 'CAPA_DIEVALUASI', $t, self::TAUTAN_AUDITEE);
        }
    }

    /** Kepala unit dan/atau kepala gudang terperiksa; kosong bila temuan tanpa unit maupun gudang. @return list<int> */
    private function auditee(TemuanAudit $t): array
    {
        $r = $this->penerima();

        return array_values(array_unique([
            ...($t->unit_id ? $r->karuUnit((int) $t->unit_id) : []),
            ...($t->gudang_id ? $r->kepalaGudang((int) $t->gudang_id) : []),
        ]));
    }
}
