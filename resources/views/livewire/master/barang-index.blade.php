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
        <div class="flex flex-wrap items-center gap-3">
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
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-28">Kode</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nama'" :direction="$sortDirection" wire:click="sort('nama')">Nama Komoditas</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tipe'" :direction="$sortDirection" wire:click="sort('tipe')">Tipe</flux:table.column>
                <flux:table.column>Kategori & Satuan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'titik_pesan'" :direction="$sortDirection" wire:click="sort('titik_pesan')" class="text-right">Titik Pesan (ROP)</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'harga_beli_terakhir'" :direction="$sortDirection" wire:click="sort('harga_beli_terakhir')" class="text-right">Harga Beli Terakhir</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->nama }}</div>
                            @if ($item->merek)
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Merek: {{ $item->merek->nama }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                            $tipeColor = match($item->tipe) {
                                'HABIS_PAKAI' => 'blue',
                                'ASET' => 'indigo',
                                'SPAREPART' => 'violet',
                                default => 'zinc',
                            };
                            @endphp
                            <flux:badge size="sm" :color="$tipeColor">
                                {{ str_replace('_', ' ', $item->tipe) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $item->kategori?->nama ?? '-' }}</div>
                            <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">Satuan: {{ $item->satuan?->nama ?? '-' }} ({{ $item->satuan?->simbol ?? $item->satuan?->kode ?? '-' }})</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                            @if($item->titik_pesan > 0)
                                <span class="font-semibold text-amber-600 dark:text-amber-400">{{ number_format($item->titik_pesan, 0, ',', '.') }}</span>
                            @else
                                <span class="text-zinc-400 dark:text-zinc-500 italic font-sans">-</span>
                            @endif
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">
                                Min: {{ $item->stok_minimum > 0 ? number_format($item->stok_minimum, 0, ',', '.') : '-' }}
                                | Max: {{ $item->stok_maksimum > 0 ? number_format($item->stok_maksimum, 0, ',', '.') : '-' }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                            <x-rupiah :value="$item->harga_beli_terakhir" class="font-semibold text-zinc-900 dark:text-zinc-100" />
                            @if ($item->harga_estimasi > 0 && $item->harga_estimasi != $item->harga_beli_terakhir)
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Est: Rp {{ number_format($item->harga_estimasi, 0, ',', '.') }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />
                                @can('update_master::barang')
                                    <flux:button wire:click="edit({{ $item->id }})" size="xs" variant="ghost" icon="pencil-square" title="Ubah Data" />
                                    <flux:button wire:click="confirmToggleStatus({{ $item->id }})" size="xs" variant="ghost" icon="arrow-path" title="Ubah Status" />
                                @endcan
                            </div>
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
            <div>
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
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Kategori Komoditas</label>
                    <select wire:model="kategori_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="">Pilih Kategori...</option>
                        @foreach ($daftarKategori as $kat)
                            <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                        @endforeach
                    </select>
                    @error('kategori_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Satuan Kemasan</label>
                    <select wire:model="satuan_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="">Pilih Satuan...</option>
                        @foreach ($daftarSatuan as $sat)
                            <option value="{{ $sat->id }}">{{ $sat->nama }} ({{ $sat->simbol ?? $sat->kode }})</option>
                        @endforeach
                    </select>
                    @error('satuan_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Merek / Brand (Opsional)</label>
                    <select wire:model="merek_id" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="">Tanpa Merek / Generic</option>
                        @foreach ($daftarMerek as $mrk)
                            <option value="{{ $mrk->id }}">{{ $mrk->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Pemasok Rekanan Utama (Opsional)</label>
                    <select wire:model="pemasok_default_id" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="">Pilih Vendor Default...</option>
                        @foreach ($daftarPemasok as $pms)
                            <option value="{{ $pms->id }}">{{ $pms->nama }}</option>
                        @endforeach
                    </select>
                </div>
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

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
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

    <flux:modal wire:model="showDetailModal" class="md:w-[42rem]">
        @if ($selectedBarang)
            <div>
                <flux:heading size="lg">Detail Komoditas: {{ $selectedBarang->nama }}</flux:heading>
                <flux:subheading>Informasi lengkap data komoditas logistik.</flux:subheading>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Kode</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedBarang->kode }}</div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Nama Komoditas</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->nama }}</div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Tipe</div>
                    <div class="mt-0.5">
                        @php
                            $detailTipeColor = match($selectedBarang->tipe) {
                                'HABIS_PAKAI' => 'blue',
                                'ASET' => 'indigo',
                                'SPAREPART' => 'violet',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$detailTipeColor">{{ str_replace('_', ' ', $selectedBarang->tipe) }}</flux:badge>
                    </div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Kategori</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->kategori?->nama ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Satuan</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->satuan?->nama ?? '-' }} ({{ $selectedBarang->satuan?->simbol ?? $selectedBarang->satuan?->kode ?? '-' }})</div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Merek</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->merek?->nama ?? '-' }}</div>
                </div>
                <div class="col-span-2 sm:col-span-3">
                    <div class="text-zinc-500 dark:text-zinc-400">Pemasok Default</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->pemasokDefault?->nama ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Stok Minimum</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->stok_minimum > 0 ? number_format($selectedBarang->stok_minimum, 0, ',', '.') : '-' }}</div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Stok Maksimum</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->stok_maksimum > 0 ? number_format($selectedBarang->stok_maksimum, 0, ',', '.') : '-' }}</div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Titik Pesan (ROP)</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedBarang->titik_pesan > 0 ? number_format($selectedBarang->titik_pesan, 0, ',', '.') : '-' }}</div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Harga Beli Terakhir</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100"><x-rupiah :value="$selectedBarang->harga_beli_terakhir" /></div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Harga Estimasi</div>
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100"><x-rupiah :value="$selectedBarang->harga_estimasi" /></div>
                </div>
                <div>
                    <div class="text-zinc-500 dark:text-zinc-400">Status</div>
                    <div class="mt-0.5"><x-status-badge :status="$selectedBarang->status" /></div>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</flux:button>
            </div>
        @endif
    </flux:modal>
</div>
