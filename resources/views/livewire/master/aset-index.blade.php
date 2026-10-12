<div class="space-y-6">
    {{-- Header Halaman & Tombol Tambah --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Register Master Aset</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Pencatatan inventaris aktiva fisik rumah sakit, nomor seri, penempatan lokasi, dan kode QR aset.</flux:subheading>
            </div>

            @can('create_master::aset')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Register Aset Baru
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Filter Lokasi Penempatan (Pills) --}}
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
            <button
                wire:click="$set('filterLokasi', '')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterLokasi === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Semua Penempatan
            </button>
            <button
                wire:click="$set('filterLokasi', 'RUANGAN')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterLokasi === 'RUANGAN' ? 'bg-white dark:bg-zinc-900 text-cyan-600 dark:text-cyan-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Di Ruangan / Unit
            </button>
            <button
                wire:click="$set('filterLokasi', 'GUDANG')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterLokasi === 'GUDANG' ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Di Gudang Logistik
            </button>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode aset, nomor seri, nama barang..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status Aset</option>
                    @foreach (\App\Enums\StatusAset::dapatDiaturManual() as $statusAset)
                        <option value="{{ $statusAset }}">{{ str_replace('_', ' ', $statusAset) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-40">
                <select wire:model.live="filterKondisi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Kondisi Fisik</option>
                    <option value="BAIK">Kondisi BAIK</option>
                    <option value="RUSAK_RINGAN">RUSAK RINGAN</option>
                    <option value="RUSAK_BERAT">RUSAK BERAT</option>
                </select>
            </div>
            @if ($search || $filterLokasi || $filterStatus || $filterKondisi || $filterUnitId || $filterGudangId)
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

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode_aset'" :direction="$sortDirection" wire:click="sort('kode_aset')" class="w-32">Kode Register</flux:table.column>
                <flux:table.column class="w-64">Nama Komoditas & Seri</flux:table.column>
                <flux:table.column class="w-48">Penempatan Lokasi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'kondisi'" :direction="$sortDirection" wire:click="sort('kondisi')" class="text-center w-28">Kondisi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nilai_perolehan'" :direction="$sortDirection" wire:click="sort('nilai_perolehan')" class="text-right w-36">Nilai Perolehan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        {{-- Kode Aset --}}
                        <flux:table.cell class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode_aset }}
                        </flux:table.cell>

                        {{-- Nama Barang & Nomor Seri --}}
                        <flux:table.cell style="white-space: normal;">
                            <button
                                type="button"
                                wire:click="showDetail({{ $item->id }})"
                                class="text-left font-medium text-xs text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-2"
                                title="Lihat Detail Aset"
                            >
                                {{ $item->barang?->nama ?? '-' }}
                            </button>
                            @if ($item->nomor_seri)
                                <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">SN: {{ $item->nomor_seri }}</div>
                            @endif
                        </flux:table.cell>

                        {{-- Lokasi Penempatan (XOR Ruangan atau Gudang) --}}
                        <flux:table.cell style="white-space: normal;">
                            @if ($item->unit_id)
                                <div class="flex items-center gap-1.5">
                                    <span class="size-1.5 rounded-full bg-cyan-500"></span>
                                    <span class="text-xs font-medium text-cyan-800 dark:text-cyan-300">Ruangan: {{ $item->unit?->nama }}</span>
                                </div>
                            @elseif ($item->gudang_id)
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                                        <span class="text-xs font-medium text-amber-800 dark:text-amber-300">Gudang: {{ $item->gudang?->nama }}</span>
                                    </div>
                                    @if ($item->lokasiGudang)
                                        <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 pl-3">Rak: {{ $item->lokasiGudang->nama }} ({{ $item->lokasiGudang->kode }})</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-red-500 italic">Tanpa Lokasi</span>
                            @endif
                        </flux:table.cell>

                        {{-- Kondisi Fisik --}}
                        <flux:table.cell class="text-center">
                            @php
                            $kondisiColor = match($item->kondisi) {
                                'BAIK' => 'emerald',
                                'RUSAK_RINGAN' => 'amber',
                                'RUSAK_BERAT' => 'rose',
                                default => 'zinc',
                            };
                            @endphp
                            <flux:badge size="sm" :color="$kondisiColor">
                                {{ str_replace('_', ' ', $item->kondisi) }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Nilai Perolehan --}}
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                            <x-rupiah :value="$item->nilai_perolehan" class="font-semibold text-zinc-900 dark:text-zinc-100" />
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">{{ $item->tanggal_perolehan->format('d/m/Y') }}</div>
                        </flux:table.cell>

                        {{-- Status Aset --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Detail Aset
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="showQr({{ $item->id }})" icon="qr-code">
                                        Cetak Stiker QR
                                    </flux:menu.item>
                                    @php
                                        $user = auth()->user();
                                        $canEditThisAset = $this->canUserEditAset($user, $item);
                                    @endphp
                                    @if ($canEditThisAset)
                                        <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil-square">
                                            Ubah Data
                                        </flux:menu.item>
                                    @endif
                                    @if ($this->canUserDeleteAset($user, $item))
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmDelete({{ $item->id }})" icon="trash" variant="danger">
                                            Hapus Data
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada unit fisik aset yang terdaftar atau sesuai filter." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Pagination Terpadu: Kiri (Showing), Tengah (Baris per halaman), Kanan (Tombol) --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    @include('livewire.master.partials.aset.modal-aset-form')

    @include('livewire.master.partials.aset.modal-qr-code')

    @include('livewire.master.partials.aset.modal-detail-aset')

    @include('livewire.master.partials.aset.modal-riwayat-sirkulasi')

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
