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
            $user && (
                $user->can('view_any_pemeliharaan::spk::perbaikan') ||
                $user->can('view_pemeliharaan::spk::perbaikan') ||
                $user->can('page_Dashboard')
            ),
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
        $isSupervisor = $user->hasRole(['super_admin', 'admin_logistik'])
            || $user->can('page_AdminLogistikDashboard')
            || $user->can('page_Dashboard');

        if (! $isSupervisor && $user->hasRole('teknisi') && (int) $spk->teknisi_id !== (int) $user->id) {
            abort(403, 'Anda hanya memiliki wewenang mencetak SPK yang ditugaskan kepada Anda.');
        }


        $pdf = Pdf::loadView('pdf.spk-maintenance', [
            'spk' => $spk,
        ])->setPaper('a4', 'portrait');

        $filename = "SPK-{$spk->kode}.pdf";

        return $pdf->stream($filename);
    }
}
