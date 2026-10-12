<?php

namespace App\Http\Controllers;

use App\Models\PenugasanMaintenance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SpkPrintController extends Controller
{
    public function cetakPdf(Request $request, int $id): Response
    {
        $user = $request->user();
        abort_unless(
            $user && $user->canAny(['view_any_pemeliharaan::spk::perbaikan', 'view_pemeliharaan::spk::perbaikan']),
            403,
            'Anda tidak memiliki izin mencetak dokumen SPK.'
        );

        $spk = PenugasanMaintenance::with([
            'permintaanMaintenance.aset.barang',
            'permintaanMaintenance.unit',
            'permintaanMaintenance.pelapor',
            'teknisi',
            'penugast',
        ])->findOrFail($id);

        // Sama dengan DaftarSpkPerbaikanIndex: hanya teknisi biasa yang dibatasi ke SPK miliknya (ADR 0001).
        $seluruhDepartemen = $user->hasRole(['super_admin', 'admin_logistik', 'kepala_teknisi', 'auditor', 'manajemen']);
        if (! $seluruhDepartemen && (int) $spk->teknisi_id !== (int) $user->id) {
            abort(403, 'Anda hanya memiliki wewenang mencetak SPK yang ditugaskan kepada Anda.');
        }


        $pdf = Pdf::loadView('pdf.spk-maintenance', [
            'spk' => $spk,
        ])->setPaper('a4', 'portrait');

        $filename = "SPK-{$spk->kode}.pdf";

        return $pdf->stream($filename);
    }
}
