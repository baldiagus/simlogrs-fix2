<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Katalog Master Barang</flux:heading>
                <flux:subheading>Daftar seluruh komoditas logistik non-medis, persediaan habis pakai, aset inventaris, dan suku cadang.</flux:subheading>
            </div>

            @can('create_master::barang')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah Barang
                </flux:button>
            @endcan
        </div>

        {{-- Filter Tipe Komoditas (Pills) --}}
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
            <button
                wire:click="$set('filterTipe', '')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterTipe === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Semua Komoditas
            </button>
            <button
                wire:click="$set('filterTipe', 'HABIS_PAKAI')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterTipe === 'HABIS_PAKAI' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Habis Pakai (ATK/ART)
            </button>
            <button
                wire:click="$set('filterTipe', 'ASET')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterTipe === 'ASET' ? 'bg-white dark:bg-zinc-900 text-indigo-600 dark:text-indigo-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Aktiva / Aset
            </button>
            <button
                wire:click="$set('filterTipe', 'SPAREPART')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterTipe === 'SPAREPART' ? 'bg-white dark:bg-zinc-900 text-violet-600 dark:text-violet-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Suku Cadang IPSRS
            </button>
        </div>

        {{-- Toolbar Filter & Pencarian GIN Trigram --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3 flex-1">
                <div class="w-full sm:w-80">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari nama barang atau kode..."
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                <div class="w-full sm:w-56">
                    <select wire:model.live="filterKategoriId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Kategori</option>
                        @foreach ($daftarKategori as $kat)
                            <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-36">
                    <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Status</option>
                        <option value="AKTIF">AKTIF</option>
                        <option value="NONAKTIF">NONAKTIF</option>
                    </select>
                </div>

                @if ($search || $filterKategoriId || $filterStatus || $filterTipe)
                    <flux:button
                        wire:click="resetFilters"
                        size="sm"
                        variant="subtle"
                        icon="x-mark"
                    >
                        Reset Filter
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-28">Kode</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nama'" :direction="$sortDirection" wire:click="sort('nama')" class="w-72">Nama Komoditas</flux:table.column>
                <flux:table.column class="w-44">Kategori</flux:table.column>
                <flux:table.column class="w-20">Satuan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'titik_pesan'" :direction="$sortDirection" wire:click="sort('titik_pesan')" class="text-right w-24">Titik Pesan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'harga_beli_terakhir'" :direction="$sortDirection" wire:click="sort('harga_beli_terakhir')" class="text-right w-32">Harga Terakhir</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell style="white-space: normal;">
                            <button
                                type="button"
                                wire:click="showDetail({{ $item->id }})"
                                class="text-left font-medium text-xs text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-2"
                                title="Klik untuk melihat detail lengkap"
                            >
                                {{ $item->nama }}
                            </button>
                            @if ($item->merek)
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">Merek: {{ $item->merek->nama }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell style="white-space: normal;">
                            <div class="text-xs text-zinc-800 dark:text-zinc-200">{{ $item->kategori?->nama ?? '-' }}</div>
                            @if (! $filterTipe)
                                @php
                                $tipeColor = match($item->tipe) {
                                    'HABIS_PAKAI' => 'blue',
                                    'ASET' => 'indigo',
                                    'SPAREPART' => 'violet',
                                    default => 'zinc',
                                };
                                @endphp
                                <div class="mt-0.5">
                                    <flux:badge size="xs" :color="$tipeColor">
                                        {{ str_replace('_', ' ', $item->tipe) }}
                                    </flux:badge>
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="w-20">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                                {{ $item->satuan?->simbol ?? $item->satuan?->nama ?? '-' }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums w-24">
                            @if($item->titik_pesan > 0)
                                <span class="font-semibold text-amber-600 dark:text-amber-400">{{ number_format($item->titik_pesan, 0, ',', '.') }}</span>
                            @else
                                <span class="text-zinc-400 dark:text-zinc-500 italic font-sans">-</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums w-32">
                            <x-rupiah :value="$item->harga_beli_terakhir" class="font-semibold text-zinc-900 dark:text-zinc-100" />
                        </flux:table.cell>
                        <flux:table.cell class="text-center w-24">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-40">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Detail Komoditas
                                    </flux:menu.item>
                                    @can('update_master::barang')
                                        <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil-square">
                                            Ubah Data
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="confirmToggleStatus({{ $item->id }})" icon="arrow-path">
                                            {{ $item->status === 'AKTIF' ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </flux:menu.item>
                                    @endcan
                                    @can('delete_master::barang')
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmDelete({{ $item->id }})" icon="trash" variant="danger">
                                            Hapus Data
                                        </flux:menu.item>
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada komoditas barang yang terdaftar atau sesuai kriteria pencarian." cols="8" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Pagination Terpadu: Kiri (Showing), Tengah (Baris per halaman), Kanan (Tombol) --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Form Tambah / Ubah Barang --}}
    <flux:modal name="modal-barang-form" wire:model="showModal" class="md:w-[42rem]">
        <form wire:submit="save" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">{{ $editingId ? 'Ubah Data Komoditas' : 'Daftarkan Barang Baru' }}</flux:heading>
                <flux:subheading>Lengkapi informasi spesifikasi, klasifikasi, stok minimum, dan harga estimasi.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <flux:input wire:model="kode" label="Kode Barang (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Tipe Komoditas</label>
                    <select wire:model="tipe" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="HABIS_PAKAI">Habis Pakai (ATK/ART)</option>
                        <option value="ASET">Aktiva / Aset Inventaris</option>
                        <option value="SPAREPART">Suku Cadang IPSRS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status Barang</label>
                    <select wire:model="status" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="AKTIF">AKTIF</option>
                        <option value="NONAKTIF">NONAKTIF</option>
                    </select>
                </div>
            </div>

            <flux:input wire:model="nama" label="Nama Lengkap Komoditas" placeholder="misal: Kertas HVS A4 80gr Sinar Dunia" required />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-searchable-select
                    model="kategori_id"
                    label="Kategori Komoditas"
                    placeholder="Pilih Kategori..."
                    :options="$daftarKategori"
                    required
                />

                <x-searchable-select
                    model="satuan_id"
                    label="Satuan Kemasan"
                    placeholder="Pilih Satuan..."
                    :options="$daftarSatuan"
                    required
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-searchable-select
                    model="merek_id"
                    label="Merek / Brand (Opsional)"
                    placeholder="Tanpa Merek / Generic"
                    :options="$daftarMerek"
                />

                <x-searchable-select
                    model="pemasok_default_id"
                    label="Pemasok Rekanan Utama (Opsional)"
                    placeholder="Pilih Vendor Default..."
                    :options="$daftarPemasok"
                />
            </div>

            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Parameter Pengendalian Stok (Buffer & ROP)</div>
                <div class="grid grid-cols-3 gap-3">
                    <x-number-input model="stok_minimum" label="Stok Minimum" placeholder="Tanpa Batas" />
                    <x-number-input model="titik_pesan" label="Titik Pesan (ROP)" placeholder="Tanpa Batas" />
                    <x-number-input model="stok_maksimum" label="Stok Maksimum" placeholder="Tanpa Batas" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-number-input model="harga_beli_terakhir" label="Harga Beli Terakhir" prefix="Rp" required />
                <x-number-input model="harga_estimasi" label="Harga Estimasi Pengadaan" prefix="Rp" required />
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan Komoditas</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />

    <flux:modal wire:model="showDetailModal" class="w-full max-w-4xl lg:max-w-5xl" style="width: 100%; max-width: 58rem;">
        @if ($selectedBarang)
            <div class="space-y-4">
                {{-- Header Modal & Identitas Utama --}}
                <div class="pb-3 border-b border-zinc-200 dark:border-zinc-800" style="padding-right: 2.5rem;">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h3 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                            {{ $selectedBarang->nama }}
                        </h3>
                        @php
                            $detailTipeColor = match($selectedBarang->tipe) {
                                'HABIS_PAKAI' => 'blue',
                                'ASET' => 'indigo',
                                'SPAREPART' => 'violet',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$detailTipeColor">{{ str_replace('_', ' ', $selectedBarang->tipe) }}</flux:badge>
                        <x-status-badge :status="$selectedBarang->status" />
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 flex items-center gap-2 flex-wrap">
                        <span class="font-mono font-semibold text-zinc-800 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                            {{ $selectedBarang->kode }}
                        </span>
                        <span>&bull;</span>
                        <span>Kategori: <strong class="text-zinc-700 dark:text-zinc-300">{{ $selectedBarang->kategori?->nama ?? '-' }}</strong></span>
                        @if ($selectedBarang->merek?->nama)
                            <span>&bull;</span>
                            <span>Merek: <strong class="text-zinc-700 dark:text-zinc-300">{{ $selectedBarang->merek->nama }}</strong></span>
                        @endif
                    </div>
                </div>

                {{-- 2 Panel Terstruktur: Spesifikasi vs Pengendalian & Finansial --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 text-xs">
                    {{-- Panel Kiri: Spesifikasi & Rekanan --}}
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-700/80 space-y-2.5">
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 pb-1.5 border-b border-zinc-200/60 dark:border-zinc-700/60 flex items-center justify-between">
                            <span>Spesifikasi & Rekanan</span>
                            <span class="text-[10px] text-zinc-400 font-mono">KLASIFIKASI</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400 block text-[11px]">Satuan Utama</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->satuan?->nama ?? '-' }} ({{ $selectedBarang->satuan?->simbol ?? $selectedBarang->satuan?->kode ?? '-' }})</span>
                            </div>
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400 block text-[11px]">Merek Dagang</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->merek?->nama ?? '-' }}</span>
                            </div>
                            <div class="col-span-2 pt-1 border-t border-zinc-200/40 dark:border-zinc-700/40">
                                <span class="text-zinc-500 dark:text-zinc-400 block text-[11px]">Pemasok Default / Rekanan</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->pemasokDefault?->nama ?? 'Belum ditentukan' }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Panel Kanan: Parameter Stok & Finansial --}}
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200/80 dark:border-zinc-700/80 space-y-2.5">
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 pb-1.5 border-b border-zinc-200/60 dark:border-zinc-700/60 flex items-center justify-between">
                            <span>Pengendalian Stok & Finansial</span>
                            <span class="text-[10px] text-zinc-400 font-mono">BUFFER & NILAI</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400 block text-[11px]">Stok Min</span>
                                <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->stok_minimum > 0 ? number_format($selectedBarang->stok_minimum, 0, ',', '.') : '-' }}</span>
                            </div>
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400 block text-[11px]">Stok Maks</span>
                                <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->stok_maksimum > 0 ? number_format($selectedBarang->stok_maksimum, 0, ',', '.') : '-' }}</span>
                            </div>
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400 block text-[11px]">Titik Pesan</span>
                                <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->titik_pesan > 0 ? number_format($selectedBarang->titik_pesan, 0, ',', '.') : '-' }}</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 pt-1 border-t border-zinc-200/40 dark:border-zinc-700/40">
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400 block text-[11px]">Harga Beli Terakhir</span>
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100"><x-rupiah :value="$selectedBarang->harga_beli_terakhir" /></span>
                            </div>
                            <div>
                                <span class="text-zinc-500 dark:text-zinc-400 block text-[11px]">Harga Estimasi</span>
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100"><x-rupiah :value="$selectedBarang->harga_estimasi" /></span>
                            </div>
                        </div>
                    </div>
                </div>

                @php
                    $asetGudang = $selectedBarang->aset->whereNotNull('gudang_id')->where('status', '!=', 'DIHAPUSKAN')->count();
                    $batchGudang = (int) $selectedBarang->stokBatch->where('status', '!=', 'HABIS')->sum('jumlah');
                    $stokGudang = ($selectedBarang->tipe === 'ASET' && $batchGudang === 0) ? $asetGudang : $batchGudang;

                    $valuasiGudang = $selectedBarang->stokBatch->where('status', '!=', 'HABIS')->sum(function ($b) {
                        return $b->jumlah * ($b->harga_satuan ?? 0);
                    });
                    if ($valuasiGudang == 0 && $selectedBarang->tipe === 'ASET') {
                        $valuasiGudang = (float) $selectedBarang->aset->whereNotNull('gudang_id')->where('status', '!=', 'DIHAPUSKAN')->sum('nilai_perolehan');
                    }

                    $asetRuangan = $selectedBarang->aset->whereNotNull('unit_id')->where('status', '!=', 'DIHAPUSKAN')->count();
                    $bhpRuangan = (int) $selectedBarang->stokRuangan->sum('jumlah');
                    $stokRuangan = $selectedBarang->tipe === 'ASET' ? $asetRuangan : $bhpRuangan;

                    $totalStokFisik = $stokGudang + $stokRuangan;
                    $stokMin = (int) $selectedBarang->stok_minimum;
                    $titikPesan = (int) $selectedBarang->titik_pesan;

                    if ($totalStokFisik == 0) {
                        $ketersediaanBadge = ['label' => 'Habis (0)', 'color' => 'rose'];
                    } elseif ($titikPesan > 0 && $totalStokFisik <= $titikPesan) {
                        $ketersediaanBadge = ['label' => 'Perlu Pesan (ROP)', 'color' => 'amber'];
                    } elseif ($stokMin > 0 && $totalStokFisik < $stokMin) {
                        $ketersediaanBadge = ['label' => 'Di Bawah Minimum', 'color' => 'amber'];
                    } else {
                        $ketersediaanBadge = ['label' => 'Aman / Optimal', 'color' => 'emerald'];
                    }
                @endphp

                {{-- 3 Kartu Ringkasan Stok Cepat --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    {{-- Saldo di Gudang --}}
                    <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs space-y-1">
                        <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400 text-xs">
                            <span class="font-semibold text-zinc-700 dark:text-zinc-300">Stok Fisik di Gudang</span>
                            <flux:icon name="building-storefront" class="size-4 text-blue-600" />
                        </div>
                        <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100">
                            {{ number_format($stokGudang, 0, ',', '.') }} <span class="text-xs font-sans text-zinc-500 font-normal">{{ $selectedBarang->satuan?->simbol ?? $selectedBarang->satuan?->kode ?? 'Unit' }}</span>
                        </div>
                        <div class="text-[11px] text-zinc-400">Tersedia di rak penyimpanan gudang</div>
                    </div>

                    {{-- Saldo di Ruangan --}}
                    <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs space-y-1">
                        <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400 text-xs">
                            <span class="font-semibold text-zinc-700 dark:text-zinc-300">Stok di Ruangan</span>
                            <flux:icon name="building-office-2" class="size-4 text-emerald-600" />
                        </div>
                        <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                            {{ number_format($stokRuangan, 0, ',', '.') }} <span class="text-xs font-sans text-zinc-500 font-normal">{{ $selectedBarang->satuan?->simbol ?? $selectedBarang->satuan?->kode ?? 'Unit' }}</span>
                        </div>
                        <div class="text-[11px] text-zinc-400">{{ $selectedBarang->tipe === 'ASET' ? 'Aktiva fisik di unit pelayanan' : 'Standby di unit-unit pelayanan' }}</div>
                    </div>

                    {{-- Valuasi & Status Ketersediaan --}}
                    <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-xs space-y-1">
                        <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400 text-xs">
                            <span class="font-semibold text-zinc-700 dark:text-zinc-300">Status Ketersediaan</span>
                            <flux:badge size="sm" :color="$ketersediaanBadge['color']">{{ $ketersediaanBadge['label'] }}</flux:badge>
                        </div>
                        <div class="text-lg font-bold font-mono text-zinc-900 dark:text-zinc-100">
                            <x-rupiah :value="$valuasiGudang" />
                        </div>
                        <div class="text-[11px] text-zinc-400">Total estimasi valuasi saldo gudang</div>
                    </div>
                </div>

                {{-- Jika Tipe Barang adalah ASET --}}
                @if ($selectedBarang->tipe === 'ASET')
                    <div class="space-y-3 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">Daftar Unit Fisik Sarana / Aset ({{ $selectedBarang->aset->count() }} Unit)</flux:heading>
                            <div class="flex items-center gap-2 text-xs font-mono">
                                <span class="text-emerald-600 font-semibold">{{ $selectedBarang->aset->where('kondisi', 'BAIK')->count() }} Baik</span> &bull;
                                <span class="text-amber-600 font-semibold">{{ $selectedBarang->aset->where('kondisi', 'RUSAK_RINGAN')->count() }} Rusak Ringan</span> &bull;
                                <span class="text-rose-600 font-semibold">{{ $selectedBarang->aset->where('kondisi', 'RUSAK_BERAT')->count() }} Rusak Berat</span>
                            </div>
                        </div>

                        <div class="overflow-x-auto max-h-56 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                                    <tr>
                                        <th class="py-2.5 px-3 font-semibold">Kode Aset</th>
                                        <th class="py-2.5 px-3 font-semibold">Lokasi Unit / Ruangan</th>
                                        <th class="py-2.5 px-3 font-semibold text-center">Kondisi</th>
                                        <th class="py-2.5 px-3 font-semibold text-center">Status</th>
                                        <th class="py-2.5 px-3 font-semibold text-center">Tgl Perolehan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @forelse ($selectedBarang->aset as $ast)
                                        <tr>
                                            <td class="py-2.5 px-3 font-mono font-semibold">{{ $ast->kode_aset ?? '-' }}</td>
                                            <td class="py-2.5 px-3 text-zinc-800 dark:text-zinc-200">{{ $ast->unit?->nama ?? $ast->gudang?->nama ?? 'Sentral' }}</td>
                                            <td class="py-2.5 px-3 text-center">
                                                <flux:badge size="xs" :color="$ast->kondisi === 'BAIK' ? 'emerald' : ($ast->kondisi === 'RUSAK_RINGAN' ? 'amber' : 'rose')">
                                                    {{ $ast->kondisi }}
                                                </flux:badge>
                                            </td>
                                            <td class="py-2.5 px-3 text-center">
                                                <flux:badge size="xs" :color="$ast->status === 'TERSEDIA' ? 'blue' : ($ast->status === 'DIGUNAKAN' ? 'emerald' : 'amber')">
                                                    {{ $ast->status }}
                                                </flux:badge>
                                            </td>
                                            <td class="py-2.5 px-3 font-mono text-center text-zinc-500">
                                                {{ $ast->tanggal_perolehan ? $ast->tanggal_perolehan->format('d/m/Y') : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-4 text-center text-zinc-500 text-xs">
                                                Belum ada unit aset terdaftar untuk komoditas ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    {{-- Jika Tipe Barang Habis Pakai / Sparepart --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                        {{-- Kolom Kiri: Rincian Batch Gudang --}}
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <flux:heading size="sm">Rincian Batch di Gudang (FEFO)</flux:heading>
                                <flux:badge size="xs" color="blue">{{ $selectedBarang->stokBatch->where('status', '!=', 'HABIS')->count() }} Batch</flux:badge>
                            </div>

                            <div class="overflow-x-auto max-h-56 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                                        <tr>
                                            <th class="py-2.5 px-2.5 font-semibold">Batch / Rak</th>
                                            <th class="py-2.5 px-2.5 font-semibold text-center">Kedaluwarsa</th>
                                            <th class="py-2.5 px-2.5 font-semibold text-right">Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                        @forelse ($selectedBarang->stokBatch->where('status', '!=', 'HABIS') as $batch)
                                            <tr>
                                                <td class="py-2 px-2.5">
                                                    <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $batch->nomor_batch }}</div>
                                                    <div class="text-[10px] text-zinc-400 font-mono">
                                                        {{ $batch->gudang?->nama ?? 'Gudang' }} &bull; {{ $batch->lokasiGudang?->nama_lokasi ?? 'Rak -' }}
                                                    </div>
                                                </td>
                                                <td class="py-2 px-2.5 text-center font-mono {{ $batch->tanggal_kedaluwarsa && $batch->tanggal_kedaluwarsa->isPast() ? 'text-rose-600 font-bold' : ($batch->tanggal_kedaluwarsa && $batch->tanggal_kedaluwarsa->diffInDays(now()) <= 90 ? 'text-amber-600 font-semibold' : 'text-zinc-600 dark:text-zinc-400') }}">
                                                    {{ $batch->tanggal_kedaluwarsa ? $batch->tanggal_kedaluwarsa->format('d/m/Y') : '-' }}
                                                </td>
                                                <td class="py-2 px-2.5 text-right font-mono font-bold text-zinc-900 dark:text-zinc-100 tabular-nums">
                                                    {{ number_format($batch->jumlah, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="py-4 text-center text-zinc-500 text-xs">
                                                    Tidak ada batch aktif di gudang.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Kolom Kanan: Rincian Sebaran di Ruangan --}}
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <flux:heading size="sm">Sebaran Persediaan di Ruangan</flux:heading>
                                <flux:badge size="xs" color="emerald">{{ $selectedBarang->stokRuangan->count() }} Unit</flux:badge>
                            </div>

                            <div class="overflow-x-auto max-h-56 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                                        <tr>
                                            <th class="py-2.5 px-2.5 font-semibold">Unit Ruangan</th>
                                            <th class="py-2.5 px-2.5 font-semibold text-right">Saldo Standby</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                        @forelse ($selectedBarang->stokRuangan as $stkR)
                                            <tr>
                                                <td class="py-2 px-2.5">
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $stkR->unit?->nama ?? '-' }}</div>
                                                    <div class="text-[10px] text-zinc-400 font-mono">Kode: {{ $stkR->unit?->kode ?? '-' }}</div>
                                                </td>
                                                <td class="py-2 px-2.5 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                                                    {{ number_format($stkR->jumlah, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @empty
                                             <tr>
                                                <td colspan="2" class="py-4 text-center text-zinc-500 text-xs">
                                                    Belum ada barang di ruangan.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        @endif
    </flux:modal>
</div>
