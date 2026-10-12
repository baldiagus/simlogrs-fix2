<div class="space-y-6">
    {{-- Header Halaman & Tombol Tambah --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Katalog Master Barang</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Daftar seluruh komoditas logistik non-medis, persediaan habis pakai, aset inventaris, dan suku cadang.</flux:subheading>
            </div>

            @can('create_master::barang')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah Barang
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
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

    @include('livewire.master.partials.barang.modal-barang-form')

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />

    @include('livewire.master.partials.barang.modal-detail-barang')
</div>
