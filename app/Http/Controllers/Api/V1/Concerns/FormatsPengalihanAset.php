<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Aset\Aset;

trait FormatsPengalihanAset
{
    protected function filterDaftarPengalihan($query, $user, bool $isGlobalAdmin, string $tab): void
    {
        if (! $isGlobalAdmin) {
            $userUnitId = $user->unit_id;
            $userGudangId = $user->gudang_id;
            $userId = $user->id;

            if ($tab === 'otorisasi') {
                $query->where('status', 'DIAJUKAN')
                    ->where(function ($q) use ($userUnitId, $userGudangId, $userId) {
                        if ($userUnitId) {
                            $q->orWhere(function ($sub) use ($userUnitId, $userId) {
                                $sub->where('tipe_asal', 'RUANGAN')
                                    ->where('unit_asal_id', $userUnitId)
                                    ->where(function ($nest) use ($userUnitId, $userId) {
                                        $nest->where('unit_tujuan_id', '!=', $userUnitId)
                                             ->orWhere('diajukan_oleh', '!=', $userId);
                                    });
                            });
                        }
                        if ($userGudangId) {
                            $q->orWhere(function ($sub) use ($userGudangId, $userId) {
                                $sub->where('tipe_asal', 'GUDANG')
                                    ->where('gudang_asal_id', $userGudangId)
                                    ->where(function ($nest) use ($userGudangId, $userId) {
                                        $nest->where('gudang_tujuan_id', '!=', $userGudangId)
                                             ->orWhere('diajukan_oleh', '!=', $userId);
                                    });
                            });
                        }
                    });
            } elseif ($tab === 'aktif') {
                $query->whereIn('status', ['DIAJUKAN', 'DISETUJUI'])
                    ->where(function ($q) use ($userUnitId, $userGudangId, $userId) {
                        $q->where('diajukan_oleh', $userId);
                        if ($userUnitId) {
                            $q->orWhere(function ($sub) use ($userUnitId) {
                                $sub->where('tipe_tujuan', 'RUANGAN')
                                    ->where('unit_tujuan_id', $userUnitId);
                            });
                        }
                        if ($userGudangId) {
                            $q->orWhere(function ($sub) use ($userGudangId) {
                                $sub->where('tipe_tujuan', 'GUDANG')
                                    ->where('gudang_tujuan_id', $userGudangId);
                            });
                        }
                    });
            } else {
                $query->whereIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN'])
                    ->where(function ($q) use ($userUnitId, $userGudangId, $userId) {
                        $q->where('diajukan_oleh', $userId);
                        if ($userUnitId) {
                            $q->orWhere('unit_asal_id', $userUnitId)
                              ->orWhere('unit_tujuan_id', $userUnitId);
                        }
                        if ($userGudangId) {
                            $q->orWhere('gudang_asal_id', $userGudangId)
                              ->orWhere('gudang_tujuan_id', $userGudangId);
                        }
                    });
            }
        } else {
            if ($tab === 'aktif') {
                $query->whereIn('status', ['DIAJUKAN', 'DISETUJUI']);
            } elseif ($tab === 'otorisasi') {
                $query->where('status', 'DIAJUKAN');
            } elseif ($tab === 'riwayat') {
                $query->whereIn('status', ['SELESAI', 'DITOLAK', 'DIBATALKAN']);
            }
        }
    }

    protected function formatItemPengalihan($item, $user, bool $isGlobalAdmin): array
    {
        $namaItem = $item->detail->first()?->aset?->barang?->nama
            ?? $item->detail->first()?->aset?->nama_aset
            ?? 'Aset Inventaris';

        $canCancel = $item->status === 'DIAJUKAN' && (
            $isGlobalAdmin ||
            (int) $item->diajukan_oleh === (int) $user->id ||
            ($user->unit_id && (int) $user->unit_id === (int) $item->unit_asal_id && $user->hasRole('kepala_unit'))
        );

        $canApprove = $item->status === 'DIAJUKAN' && (
            $isGlobalAdmin ||
            ($user->unit_id && (int) $user->unit_id === (int) $item->unit_asal_id && $user->hasRole(['kepala_unit', 'super_admin', 'admin_logistik'])) ||
            ($user->gudang_id && (int) $user->gudang_id === (int) $item->gudang_asal_id && $user->hasRole(['kepala_gudang', 'super_admin', 'admin_logistik']))
        );

        $canReceive = $item->status === 'DISETUJUI' && (
            $isGlobalAdmin ||
            ($user->unit_id && (int) $user->unit_id === (int) $item->unit_tujuan_id) ||
            ($user->gudang_id && (int) $user->gudang_id === (int) $item->gudang_tujuan_id)
        );

        return [
            'id'                 => $item->id,
            'kode'               => $item->kode,
            'tipe'               => 'ASET',
            'tipe_asal'          => $item->tipe_asal,
            'asal_nama'          => $item->nama_lokasi_asal,
            'unit_asal_id'       => $item->unit_asal_id,
            'gudang_asal_id'     => $item->gudang_asal_id,
            'tipe_tujuan'        => $item->tipe_tujuan,
            'tujuan_nama'        => $item->nama_lokasi_tujuan,
            'unit_tujuan_id'     => $item->unit_tujuan_id,
            'gudang_tujuan_id'   => $item->gudang_tujuan_id,
            'status'             => $item->status,
            'tanggal_pengalihan' => $item->tanggal_pengalihan?->format('Y-m-d') ?? '',
            'nama_item'          => $namaItem,
            'jumlah'             => $item->detail->count(),
            'alasan'             => $item->alasan,
            'catatan'            => $item->catatan,
            'pengaju'            => $item->pengaju?->nama ?? 'Staf',
            'penyetuju'          => $item->penyetuju?->nama,
            'penerima'           => $item->penerima?->nama,
            'can_cancel'         => $canCancel,
            'can_approve'        => $canApprove,
            'can_receive'        => $canReceive,
            'items'              => $item->detail->map(fn ($d) => [
                'id'                   => $d->id,
                'aset_id'              => $d->aset_id,
                'nama_barang'          => $d->aset?->barang?->nama ?? 'Aset',
                'kode_aset'            => $d->aset?->kode_aset ?? '-',
                'nomor_seri'           => $d->aset?->nomor_seri ?? '-',
                'kondisi_sebelum'      => $d->kondisi_sebelum ?? 'BAIK',
                'kondisi_saat_diterima'=> $d->kondisi_saat_diterima,
            ]),
        ];
    }
}
