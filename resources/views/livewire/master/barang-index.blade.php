<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
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

    {{-- Filter Tipe Komoditas (Tab Pills) & Search Toolbar --}}
    <div class="space-y-4">
        {{-- Filter Tipe Komoditas --}}
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
            <button
                wire:click="$set('filterTipe', '')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterTipe === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Semua Komoditas
            </button>
            <button
                wire:click="$set('filterTipe', 'HABIS_PAKAI')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterTipe === 'HABIS_PAKAI' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Habis Pakai (ATK/ART)
            </button>
            <button
                wire:click="$set('filterTipe', 'ASET')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterTipe === 'ASET' ? 'bg-white dark:bg-zinc-900 text-indigo-600 dark:text-indigo-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Aktiva / Aset
            </button>
            <button
                wire:click="$set('filterTipe', 'SPAREPART')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterTipe === 'SPAREPART' ? 'bg-white dark:bg-zinc-900 text-violet-600 dark:text-violet-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Suku Cadang IPSRS
            </button>
        </div>

        {{-- Toolbar Filter & Pencarian GIN Trigram --}}
        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex flex-col lg:flex-row gap-3 items-center justify-between">
                <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    {{-- Pencarian Terindeks GIN Trigram --}}
                    <div class="w-full sm:w-80">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari nama barang atau kode..."
                            icon="magnifying-glass"
                            clearable
                        />
                    </div>

                    {{-- Filter Kategori --}}
                    <div class="w-full sm:w-56">
                        <select wire:model.live="filterKategoriId" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            <option value="">Semua Kategori</option>
                            @foreach ($daftarKategori as $kat)
                                <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filter Status --}}
                    <div class="w-full sm:w-36">
                        <select wire:model.live="filterStatus" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            <option value="">Semua Status</option>
                            <option value="AKTIF">AKTIF</option>
                            <option value="NONAKTIF">NONAKTIF</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-xs text-zinc-500 self-end lg:self-center">
                    <span>Per halaman:</span>
                    <select wire:model.live="perPage" class="border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 rounded-md px-2 py-1 text-xs">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Tabel Data --}}
    <flux:card class="p-0 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
        <flux:table :paginate="$items">
            <flux:table.columns sticky>
                <flux:table.column class="w-28">Kode</flux:table.column>
                <flux:table.column>Nama Komoditas</flux:table.column>
                <flux:table.column>Tipe</flux:table.column>
                <flux:table.column>Kategori & Satuan</flux:table.column>
                <flux:table.column class="text-right">Titik Pesan (ROP)</flux:table.column>
                <flux:table.column class="text-right">Harga Beli Terakhir</flux:table.column>
                <flux:table.column class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        {{-- Kode --}}
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>

                        {{-- Nama & Merek --}}
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->nama }}</div>
                            @if ($item->merek)
                                <div class="text-[11px] text-zinc-400">Merek: {{ $item->merek->nama }}</div>
                            @endif
                        </flux:table.cell>

                        {{-- Tipe --}}
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

                        {{-- Kategori & Satuan --}}
                        <flux:table.cell>
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">{{ $item->kategori?->nama ?? '-' }}</div>
                            <div class="font-mono text-[11px] text-zinc-400">Satuan: {{ $item->satuan?->nama ?? '-' }} ({{ $item->satuan?->simbol ?? $item->satuan?->kode ?? '-' }})</div>
                        </flux:table.cell>

                        {{-- Buffer & ROP --}}
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                            <span class="font-semibold {{ $item->titik_pesan > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-400' }}">
                                {{ number_format($item->titik_pesan, 0, ',', '.') }}
                            </span>
                            <div class="text-[10px] text-zinc-400">Min: {{ $item->stok_minimum }} | Max: {{ $item->stok_maksimum }}</div>
                        </flux:table.cell>

                        {{-- Harga Beli --}}
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                            <x-rupiah :value="$item->harga_beli_terakhir" class="font-semibold text-zinc-900 dark:text-zinc-100" />
                            @if ($item->harga_estimasi > 0 && $item->harga_estimasi != $item->harga_beli_terakhir)
                                <div class="text-[10px] text-zinc-400">Est: Rp {{ number_format($item->harga_estimasi, 0, ',', '.') }}</div>
                            @endif
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @can('update_master::barang')
                                    <flux:button wire:click="edit({{ $item->id }})" size="xs" variant="ghost" icon="pencil-square" title="Ubah Data" />
                                    <flux:button wire:click="toggleStatus({{ $item->id }})" size="xs" variant="ghost" icon="arrow-path" title="Ubah Status" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada komoditas barang yang terdaftar atau sesuai kriteria pencarian." cols="8" />
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal Form Tambah / Ubah Barang --}}
    <flux:modal name="modal-barang-form" wire:model="showModal" class="md:w-[42rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Data Komoditas' : 'Daftarkan Barang Baru' }}</flux:heading>
                <flux:subheading>Lengkapi informasi spesifikasi, klasifikasi, stok minimum, dan harga estimasi.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Kode Barang --}}
                <flux:input
                    wire:model="kode"
                    label="Kode Barang (Opsional)"
                    placeholder="Auto: BRG-YYYY-XXX"
                    uppercase
                />

                {{-- Tipe Komoditas --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Tipe Komoditas</label>
                    <select wire:model="tipe" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="HABIS_PAKAI">Habis Pakai (ATK/ART)</option>
                        <option value="ASET">Aktiva / Aset Inventaris</option>
                        <option value="SPAREPART">Suku Cadang IPSRS</option>
                    </select>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status Barang</label>
                    <select wire:model="status" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="AKTIF">AKTIF</option>
                        <option value="NONAKTIF">NONAKTIF</option>
                    </select>
                </div>
            </div>

            {{-- Nama Barang --}}
            <flux:input wire:model="nama" label="Nama Lengkap Komoditas" placeholder="misal: Kertas HVS A4 80gr Sinar Dunia" required />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Kategori --}}
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

                {{-- Satuan --}}
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
                {{-- Merek --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Merek / Brand (Opsional)</label>
                    <select wire:model="merek_id" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="">Tanpa Merek / Generic</option>
                        @foreach ($daftarMerek as $mrk)
                            <option value="{{ $mrk->id }}">{{ $mrk->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Pemasok Rekanan --}}
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

            {{-- Parameter Buffer Stok --}}
            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Parameter Pengendalian Stok (Buffer & ROP)</div>
                <div class="grid grid-cols-3 gap-3">
                    <flux:input wire:model="stok_minimum" label="Stok Minimum" type="number" min="0" required />
                    <flux:input wire:model="titik_pesan" label="Titik Pesan (ROP)" type="number" min="0" required />
                    <flux:input wire:model="stok_maksimum" label="Stok Maksimum" type="number" min="0" required />
                </div>
            </div>

            {{-- Estimasi & Finansial --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model="harga_beli_terakhir" label="Harga Beli Terakhir (Rp)" type="number" min="0" step="0.01" required />
                <flux:input wire:model="harga_estimasi" label="Harga Estimasi Pengadaan (Rp)" type="number" min="0" step="0.01" required />
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
</div>
