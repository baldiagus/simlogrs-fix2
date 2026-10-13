<?php

namespace App\Livewire\Aset\Concerns;

use App\Enums\Aset\StatusAset;
use App\Enums\Aset\StatusPeminjaman;
use App\Enums\Aset\StatusPengalihan;
use App\Models\Aset\Aset;
use App\Models\Master\Barang;
use App\Models\Master\Gudang;
use App\Models\Aset\PengalihanAset;
use App\Models\Aset\PengalihanAsetDetail;
use App\Models\Master\Unit;

trait HasPenyediaPengalihan
{
    public function getMaxQuantityProperty(): int
    {
        if (! $this->selected_penyedia_key) {
            return 1;
        }

        $providers = $this->getPenyediaAsetList();
        $provider = collect($providers)->firstWhere('key', $this->selected_penyedia_key);

        return $provider ? max(1, $provider['jumlah']) : 1;
    }

    public function getDaftarBarangAsetTersedia()
    {
        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        $ownUnitId = ! $isGlobalAdmin ? $user?->unit_id : null;
        $ownGudangId = ! $isGlobalAdmin ? $user?->gudang_id : null;

        $constraint = function ($q) use ($ownUnitId, $ownGudangId) {
            $q->whereIn('status', StatusAset::dapatDialihkan())
                ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
                ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                    $sub->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
                })
                ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                    $sub->whereIn('status', StatusPeminjaman::aktif());
                })
                ->when($ownUnitId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('unit_id', '!=', $ownUnitId)->orWhereNull('unit_id')))
                ->when($ownGudangId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('gudang_id', '!=', $ownGudangId)->orWhereNull('gudang_id')));
        };

        return Barang::where('status', 'AKTIF')
            ->whereHas('aset', $constraint)
            ->withCount(['aset' => $constraint])
            ->with('satuan')
            ->orderBy('nama')
            ->get();
    }

    public function getPenyediaAsetList(): array
    {
        if (! $this->selected_barang_id) {
            return [];
        }

        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        $ownUnitId = ! $isGlobalAdmin ? $user?->unit_id : null;
        $ownGudangId = ! $isGlobalAdmin ? $user?->gudang_id : null;
        $userUnitId = $user?->unit_id;
        $userGudangId = $user?->gudang_id;

        $assets = Aset::where('barang_id', $this->selected_barang_id)
            ->whereIn('status', StatusAset::dapatDialihkan())
            ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN'])
            ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                $sub->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
            })
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                $sub->whereIn('status', StatusPeminjaman::aktif());
            })
            ->when($ownUnitId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('unit_id', '!=', $ownUnitId)->orWhereNull('unit_id')))
            ->when($ownGudangId, fn ($sub) => $sub->where(fn ($sq) => $sq->where('gudang_id', '!=', $ownGudangId)->orWhereNull('gudang_id')))
            ->with(['unit', 'gudang'])
            ->get();
        $providers = [];

        // Kelompokkan penyedia ruangan unit
        $units = $assets->whereNotNull('unit_id')->groupBy('unit_id');
        foreach ($units as $unitId => $group) {
            $unit = $group->first()->unit;
            if (! $unit || $unit->status !== 'AKTIF') continue;

            $isOwnUnit = $userUnitId && (int) $userUnitId === (int) $unitId;

            $providers[] = [
                'key'        => "RUANGAN_{$unitId}",
                'tipe'       => 'RUANGAN',
                'id'         => $unitId,
                'nama'       => $unit->nama,
                'kode'       => $unit->kode,
                'jumlah'     => $group->count(),
                'is_own'     => $isOwnUnit,
                'aset_ids'   => $group->pluck('id')->toArray(),
                'kode_asets' => $group->pluck('kode_aset')->implode(', '),
            ];
        }

        // Kelompokkan penyedia fasilitas gudang
        $gudangs = $assets->whereNotNull('gudang_id')->groupBy('gudang_id');
        foreach ($gudangs as $gudangId => $group) {
            $gudang = $group->first()->gudang;
            if (! $gudang || $gudang->status !== 'AKTIF') continue;

            $isOwnGudang = $userGudangId && (int) $userGudangId === (int) $gudangId;

            $providers[] = [
                'key'        => "GUDANG_{$gudangId}",
                'tipe'       => 'GUDANG',
                'id'         => $gudangId,
                'nama'       => $gudang->nama,
                'kode'       => $gudang->kode,
                'jumlah'     => $group->count(),
                'is_own'     => $isOwnGudang,
                'aset_ids'   => $group->pluck('id')->toArray(),
                'kode_asets' => $group->pluck('kode_aset')->implode(', '),
            ];
        }

        if ($this->searchPenyedia) {
            $term = strtolower(trim($this->searchPenyedia));
            $providers = array_values(array_filter($providers, function ($p) use ($term) {
                return str_contains(strtolower($p['nama']), $term) ||
                       str_contains(strtolower($p['kode']), $term);
            }));
        }

        return $providers;
    }

    public function updatedTipeAsal(): void
    {
        $this->selected_aset_ids = [];
        if ($this->tipe_asal === 'RUANGAN' && ! $this->unit_asal_id) {
            $this->unit_asal_id = Unit::where('status', 'AKTIF')->value('id');
            $this->gudang_asal_id = null;
        } elseif ($this->tipe_asal === 'GUDANG' && ! $this->gudang_asal_id) {
            $this->gudang_asal_id = Gudang::where('status', 'AKTIF')->value('id');
            $this->unit_asal_id = null;
        }
    }

    public function updatedUnitAsalId(): void
    {
        $this->selected_aset_ids = [];
    }

    public function updatedGudangAsalId(): void
    {
        $this->selected_aset_ids = [];
    }

    public function updatedTipeTujuan(): void
    {
        if ($this->tipe_tujuan === 'RUANGAN') {
            $this->gudang_tujuan_id = null;
            $this->lokasi_gudang_tujuan_id = null;
        } else {
            $this->unit_tujuan_id = null;
        }
    }

    protected function resolveLokasiPengalihan($user, bool $isGlobalAdmin): bool
    {
        if ($this->selected_penyedia_key) {
            $providers = $this->getPenyediaAsetList();
            $provider = collect($providers)->firstWhere('key', $this->selected_penyedia_key);
            if (! $provider) {
                $this->addError('selected_penyedia_key', 'Lokasi penyedia aset tidak valid.');
                return false;
            }

            $maxAvailable = count($provider['aset_ids']);
            $this->validate([
                'quantity' => "required|integer|min:1|max:{$maxAvailable}",
            ], [
                'quantity.required' => 'Jumlah unit aset yang dialihkan wajib diisi.',
                'quantity.min'      => 'Jumlah unit minimal 1 unit.',
                'quantity.max'      => "Jumlah unit melebihi stok tersedia (maksimal {$maxAvailable} unit).",
            ]);

            $this->selected_aset_ids = array_slice($provider['aset_ids'], 0, (int) $this->quantity);
            $this->tipe_asal = $provider['tipe'];
            if ($provider['tipe'] === 'RUANGAN') {
                $this->unit_asal_id = $provider['id'];
                $this->gudang_asal_id = null;
            } else {
                $this->gudang_asal_id = $provider['id'];
                $this->unit_asal_id = null;
            }

            if (! $isGlobalAdmin) {
                if ($user?->unit_id) {
                    $this->tipe_tujuan = 'RUANGAN';
                    $this->unit_tujuan_id = $user->unit_id;
                    $this->gudang_tujuan_id = null;
                } elseif ($user?->gudang_id) {
                    $this->tipe_tujuan = 'GUDANG';
                    $this->gudang_tujuan_id = $user->gudang_id;
                    $this->unit_tujuan_id = null;
                }
            }
        } else {
            // Proteksi fallback jika menggunakan form manual
            if (! $isGlobalAdmin) {
                if ($user?->unit_id) {
                    $this->tipe_asal = 'RUANGAN';
                    $this->unit_asal_id = $user->unit_id;
                    $this->gudang_asal_id = null;
                } elseif ($user?->gudang_id) {
                    $this->tipe_asal = 'GUDANG';
                    $this->gudang_asal_id = $user->gudang_id;
                    $this->unit_asal_id = null;
                }
            }
        }

        return true;
    }

    protected function hitungKpiPengalihan($user, bool $isGlobalAdmin): array
    {
        $countAktifQuery = PengalihanAset::whereIn('status', StatusPengalihan::aktif());
        if (! $isGlobalAdmin) {
            $countAktifQuery->where(function ($q) use ($user) {
                if ($user?->unit_id) {
                    $q->where(fn ($sub) => $sub->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $user->unit_id))
                      ->orWhere('diajukan_oleh', $user->id);
                } elseif ($user?->gudang_id) {
                    $q->where(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id))
                      ->orWhere('diajukan_oleh', $user->id);
                }
            });
        }
        $countAktif = $countAktifQuery->count();

        $countOtorisasiQuery = PengalihanAset::where('status', StatusPengalihan::Diajukan->value);
        if (! $isGlobalAdmin) {
            $countOtorisasiQuery->where(function ($q) use ($user) {
                if ($user?->unit_id) {
                    $q->where('tipe_asal', 'RUANGAN')
                      ->where('unit_asal_id', $user->unit_id)
                      ->where(function ($sub) use ($user) {
                          $sub->where('tipe_tujuan', '!=', 'RUANGAN')
                              ->orWhere('unit_tujuan_id', '!=', $user->unit_id);
                      });
                } elseif ($user?->gudang_id) {
                    $q->where('tipe_asal', 'GUDANG')
                      ->where('gudang_asal_id', $user->gudang_id)
                      ->where(function ($sub) use ($user) {
                          $sub->where('tipe_tujuan', '!=', 'GUDANG')
                              ->orWhere('gudang_tujuan_id', '!=', $user->gudang_id);
                      });
                }
            });
        }
        $countOtorisasi = $countOtorisasiQuery->count();

        $kpiBerjalan = $countAktif;
        $kpiMenungguTerima = (clone $countAktifQuery)->where('status', 'DISETUJUI')->count();

        $riwayatQuery = PengalihanAset::where('status', 'SELESAI');
        if (! $isGlobalAdmin) {
            $riwayatQuery->where(function ($q) use ($user) {
                if ($user?->unit_id) {
                    $q->where(fn ($sub) => $sub->where('tipe_asal', 'RUANGAN')->where('unit_asal_id', $user->unit_id))
                      ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $user->unit_id));
                } elseif ($user?->gudang_id) {
                    $q->where(fn ($sub) => $sub->where('tipe_asal', 'GUDANG')->where('gudang_asal_id', $user->gudang_id))
                      ->orWhere(fn ($sub) => $sub->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id));
                }
            });
        }
        $kpiSelesaiBulanIni = (clone $riwayatQuery)
            ->whereMonth('tanggal_serah_terima', now()->month)
            ->whereYear('tanggal_serah_terima', now()->year)
            ->count();

        $kpiTotalAset = PengalihanAsetDetail::whereHas('pengalihanAset', function ($q) use ($isGlobalAdmin, $user) {
            $q->where('status', 'SELESAI');
            if (! $isGlobalAdmin) {
                $q->where(function ($sub) use ($user) {
                    if ($user?->unit_id) {
                        $sub->where(fn ($s) => $s->where('tipe_asal', 'RUANGAN')->where('unit_asal_id', $user->unit_id))
                            ->orWhere(fn ($s) => $s->where('tipe_tujuan', 'RUANGAN')->where('unit_tujuan_id', $user->unit_id));
                    } elseif ($user?->gudang_id) {
                        $sub->where(fn ($s) => $s->where('tipe_asal', 'GUDANG')->where('gudang_asal_id', $user->gudang_id))
                            ->orWhere(fn ($s) => $s->where('tipe_tujuan', 'GUDANG')->where('gudang_tujuan_id', $user->gudang_id));
                    }
                });
            }
        })->count();

        return [
            'countAktif'         => $countAktif,
            'countOtorisasi'     => $countOtorisasi,
            'kpiBerjalan'        => $kpiBerjalan,
            'kpiMenungguTerima'  => $kpiMenungguTerima,
            'kpiSelesaiBulanIni' => $kpiSelesaiBulanIni,
            'kpiTotalAset'       => $kpiTotalAset,
        ];
    }

    protected function queryAsetTersediaModal()
    {
        if (! $this->showCreateModal) {
            return collect();
        }

        $asetQuery = Aset::with('barang')
            ->whereIn('status', StatusAset::dapatDialihkan())
            ->whereNotIn('kondisi', ['RUSAK_BERAT', 'DIHAPUSKAN']);

        if ($this->tipe_asal === 'RUANGAN' && $this->unit_asal_id) {
            $asetQuery->where('unit_id', $this->unit_asal_id);
        } elseif ($this->tipe_asal === 'GUDANG' && $this->gudang_asal_id) {
            $asetQuery->where('gudang_id', $this->gudang_asal_id);
        } else {
            $asetQuery->whereRaw('1=0');
        }

        if ($this->searchAsetModal) {
            $asetQuery->where(function ($q) {
                $q->where('kode_aset', 'ilike', "%{$this->searchAsetModal}%")
                  ->orWhere('nomor_seri', 'ilike', "%{$this->searchAsetModal}%")
                  ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->searchAsetModal}%"));
            });
        }

        return $asetQuery->orderBy('kode_aset')->get();
    }
}
