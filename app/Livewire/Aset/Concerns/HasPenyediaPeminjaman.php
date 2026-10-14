<?php

namespace App\Livewire\Aset\Concerns;

use App\Enums\Aset\StatusAset;
use App\Enums\Aset\StatusPeminjaman;
use App\Models\Aset\Aset;
use App\Models\Master\Barang;
use App\Models\Aset\PermintaanPeminjaman;

trait HasPenyediaPeminjaman
{
    /**
     * Menjalankan proses apply peminjaman scope pada komponen HasPenyediaPeminjaman.
     */
    protected function applyPeminjamanScope($query, $user, bool $isGlobalAdmin): void
    {
        if ($user?->unit_id) {
            $query->where('unit_id', $user->unit_id);
            return;
        }

        if ($user?->gudang_id) {
            $query->where('tipe_penyedia', 'GUDANG')
                  ->where('gudang_penyedia_id', $user->gudang_id);
            return;
        }

        if ($isGlobalAdmin) {
            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * Menjalankan proses apply katalog scope pada komponen HasPenyediaPeminjaman.
     */
    protected function applyKatalogScope($query, $user, bool $isGlobalAdmin): void
    {
        if ($isGlobalAdmin) {
            return;
        }

        if ($user?->unit_id) {
            $query->where('unit_id', $user->unit_id);
        } elseif ($user?->gudang_id) {
            $query->where('gudang_id', $user->gudang_id);
        }
    }

    /**
     * Pilih unit atau gudang penyedia aset berdasarkan kunci entitas.
     */
    public function selectPenyediaKey(?string $key): void
    {
        $this->selected_penyedia_key = $key;
        $this->selected_aset_id = null;

        if (! $key || ! str_contains($key, '_')) {
            $this->unit_penyedia_id = null;
            $this->gudang_penyedia_id = null;
            return;
        }

        $providers = $this->getPenyediaAsetList();
        $provider = collect($providers)->firstWhere('key', $key);
        if ($provider) {
            $maxAvailable = $provider['jumlah'];
            if ($this->quantity > $maxAvailable) {
                $this->quantity = max(1, $maxAvailable);
            }
        }

        [$tipe, $id] = explode('_', $key, 2);
        $id = (int) $id;

        if ($tipe === 'RUANGAN') {
            $this->tipe_penyedia = 'RUANGAN';
            $this->unit_penyedia_id = $id;
            $this->gudang_penyedia_id = null;

            $aset = Aset::where('barang_id', $this->selected_barang_id)
                ->where('unit_id', $id)
                ->where('status', StatusAset::Tersedia->value)
                ->where('kondisi', 'BAIK')
                ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                    $sub->whereIn('status', StatusPeminjaman::aktif());
                })
                ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                    $sub->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
                })
                ->first();
            $this->selected_aset_id = $aset?->id;
        } else {
            $this->tipe_penyedia = 'GUDANG';
            $this->gudang_penyedia_id = $id;
            $this->unit_penyedia_id = null;

            $aset = Aset::where('barang_id', $this->selected_barang_id)
                ->where('gudang_id', $id)
                ->where('status', StatusAset::Tersedia->value)
                ->where('kondisi', 'BAIK')
                ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                    $sub->whereIn('status', StatusPeminjaman::aktif());
                })
                ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                    $sub->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
                })
                ->first();
            $this->selected_aset_id = $aset?->id;
        }
    }

    /**
     * Computed property Livewire untuk mengakses data max quantity.
     */
    public function getMaxQuantityProperty(): int
    {
        if (! $this->selected_penyedia_key) {
            return 1;
        }

        $providers = $this->getPenyediaAsetList();
        $provider = collect($providers)->firstWhere('key', $this->selected_penyedia_key);

        return $provider ? max(1, $provider['jumlah']) : 1;
    }

    /**
     * Dapatkan koleksi barang kategori aset yang tersedia untuk transaksi.
     */
    public function getDaftarBarangAsetTersedia()
    {
        $user = auth()->user();
        $isGlobalAdmin = (bool) ($user && $user->hasRole(['super_admin', 'admin_logistik']));
        $ownUnitId = ! $isGlobalAdmin ? $user?->unit_id : null;

        $constraint = function ($q) use ($ownUnitId) {
            $q->where('status', StatusAset::Tersedia->value)->where('kondisi', 'BAIK')
                ->where(function ($loc) {
                    $loc->whereNull('unit_id')->orWhereHas('unit', fn ($u) => $u->where('status', 'AKTIF'));
                })
                ->where(function ($loc) {
                    $loc->whereNull('gudang_id')->orWhereHas('gudang', fn ($g) => $g->where('status', 'AKTIF'));
                })
                ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                    $sub->whereIn('status', StatusPeminjaman::aktif());
                })
                ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                    $sub->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
                })
                ->when($ownUnitId, fn ($sub) => $sub->where('unit_id', '!=', $ownUnitId));
        };

        return Barang::where('status', 'AKTIF')
            ->whereHas('aset', $constraint)
            ->withCount(['aset' => $constraint])
            ->with('satuan')
            ->orderBy('nama')
            ->get();
    }

    /**
     * Dapatkan daftar entitas penyedia yang memiliki ketersediaan aset terkait.
     */
    public function getPenyediaAsetList(): array
    {
        if (! $this->selected_barang_id) {
            return [];
        }

        $user = auth()->user();
        $userUnitId = $user?->unit_id;

        $assets = Aset::where('barang_id', $this->selected_barang_id)
            ->where('status', StatusAset::Tersedia->value)
            ->where('kondisi', 'BAIK')
            ->where(function ($loc) {
                $loc->whereNull('unit_id')->orWhereHas('unit', fn ($u) => $u->where('status', 'AKTIF'));
            })
            ->where(function ($loc) {
                $loc->whereNull('gudang_id')->orWhereHas('gudang', fn ($g) => $g->where('status', 'AKTIF'));
            })
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                $sub->whereIn('status', StatusPeminjaman::aktif());
            })
            ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                $sub->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
            })
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

            $providers[] = [
                'key'        => "GUDANG_{$gudangId}",
                'tipe'       => 'GUDANG',
                'id'         => $gudangId,
                'nama'       => $gudang->nama,
                'kode'       => $gudang->kode,
                'jumlah'     => $group->count(),
                'is_own'     => false,
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

    /**
     * Hitung ringkasan statistik dan metrik peminjaman aset pengguna.
     */
    protected function hitungStatistikPeminjaman($user, bool $isGlobalAdmin): array
    {
        $countAktifQuery = PermintaanPeminjaman::whereIn('status', StatusPeminjaman::aktif());
        $this->applyPeminjamanScope($countAktifQuery, $user, $isGlobalAdmin);
        $countAktif = $countAktifQuery->count();

        $countKatalogQuery = Aset::where('status', StatusAset::Tersedia->value)->where('kondisi', 'BAIK')
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($q) {
                $q->whereIn('status', StatusPeminjaman::aktif());
            })
            ->whereDoesntHave('permintaanMaintenance', function ($q) {
                $q->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
            });
        $this->applyKatalogScope($countKatalogQuery, $user, $isGlobalAdmin);
        $countKatalog = $countKatalogQuery->count();

        return [$countAktif, $countKatalog];
    }

    /**
     * Dapatkan builder query daftar peminjaman aset terfilter.
     */
    protected function queryDaftarPeminjaman($user, bool $isGlobalAdmin)
    {
        $query = PermintaanPeminjaman::with([
            'unitPenyedia',
            'gudangPenyedia',
            'unitPeminjam',
            'pengaju',
            'detail.aset.barang',
        ]);

        $this->applyPeminjamanScope($query, $user, $isGlobalAdmin);

        $query->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode', 'ilike', "%{$this->search}%")
                        ->orWhere('keperluan', 'ilike', "%{$this->search}%")
                        ->orWhereHas('unitPeminjam', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('unitPenyedia', fn ($u) => $u->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('gudangPenyedia', fn ($g) => $g->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('detail.aset.barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"))
                        ->orWhereHas('detail.aset', fn ($a) => $a->where('kode_aset', 'ilike', "%{$this->search}%"));
                });
            });

        return $query
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);
    }

    /**
     * Dapatkan builder query katalog aset yang siap dipinjam.
     */
    protected function queryDaftarKatalogAset($user, bool $isGlobalAdmin)
    {
        $queryKatalog = Aset::with(['barang.satuan', 'unit', 'gudang', 'lokasiGudang'])
            ->where('status', StatusAset::Tersedia->value)
            ->where('kondisi', 'BAIK')
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($q) {
                $q->whereIn('status', StatusPeminjaman::aktif());
            })
            ->whereDoesntHave('permintaanMaintenance', function ($q) {
                $q->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
            });
        $this->applyKatalogScope($queryKatalog, $user, $isGlobalAdmin);

        $queryKatalog->when($this->filterKatalogLokasi === 'RUANGAN', fn ($q) => $q->whereNotNull('unit_id'))
            ->when($this->filterKatalogLokasi === 'GUDANG', fn ($q) => $q->whereNotNull('gudang_id'))
            ->when($this->filterKatalogUnitId, fn ($q) => $q->where('unit_id', $this->filterKatalogUnitId))
            ->when($this->filterKatalogGudangId, fn ($q) => $q->where('gudang_id', $this->filterKatalogGudangId))
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('kode_aset', 'ilike', "%{$this->search}%")
                        ->orWhere('nomor_seri', 'ilike', "%{$this->search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama', 'ilike', "%{$this->search}%"));
                });
            });

        return $queryKatalog
            ->orderBy($this->sortBy ?: 'id', $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($this->perPage);
    }
}
