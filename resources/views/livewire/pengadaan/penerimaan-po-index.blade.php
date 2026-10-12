<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Penerimaan Barang (Inbound)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Pantau status pengiriman PO vendor dan catat penerimaan fisik logistik.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Hub Pengadaan --}}
        <x-pengadaan-transaksi-tabs active="rcv" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Tab Switcher (Dual-Mode: Tracking PO & Riwayat RCV) --}}
        <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-2">
            <button
                wire:click="switchTab('tracking')"
                type="button"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'tracking' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon.truck class="size-4" />
                <span>Menunggu Penerimaan (Tracking PO)</span>
                <span class="ml-1 text-xs px-2 py-0.5 rounded-full {{ $activeTab === 'tracking' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' }}">
                    {{ $countTracking }}
                </span>
            </button>

            <button
                wire:click="switchTab('riwayat')"
                type="button"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'riwayat' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon.inbox-arrow-down class="size-4" />
                <span>Riwayat Penerimaan (RCV)</span>
                <span class="ml-1 text-xs px-2 py-0.5 rounded-full {{ $activeTab === 'riwayat' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' }}">
                    {{ $countRiwayat }}
                </span>
            </button>
        </div>

        {{-- Filter Status Pills & Search Toolbar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
            {{-- Filter Status Pills --}}
            <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
                @if ($activeTab === 'tracking')
                    <button
                        wire:click="$set('filterStatus', '')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Semua
                    </button>
                    <button
                        wire:click="$set('filterStatus', '{{ \App\Enums\StatusPesananPembelian::Diterbitkan->value }}')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === \App\Enums\StatusPesananPembelian::Diterbitkan->value ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Diterbitkan
                    </button>
                    <button
                        wire:click="$set('filterStatus', '{{ \App\Enums\StatusPesananPembelian::DikirimVendor->value }}')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === \App\Enums\StatusPesananPembelian::DikirimVendor->value ? 'bg-white dark:bg-zinc-900 text-indigo-600 dark:text-indigo-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Dikirim Vendor
                    </button>
                    <button
                        wire:click="$set('filterStatus', '{{ \App\Enums\StatusPesananPembelian::DiterimaSebagian->value }}')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === \App\Enums\StatusPesananPembelian::DiterimaSebagian->value ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Diterima Sebagian
                    </button>
                @else
                    <button
                        wire:click="$set('filterStatus', '')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Semua
                    </button>
                    <button
                        wire:click="$set('filterStatus', '{{ \App\Enums\StatusPenerimaanPo::Lengkap->value }}')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === \App\Enums\StatusPenerimaanPo::Lengkap->value ? 'bg-white dark:bg-zinc-900 text-emerald-600 dark:text-emerald-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Lengkap
                    </button>
                    <button
                        wire:click="$set('filterStatus', '{{ \App\Enums\StatusPenerimaanPo::Sebagian->value }}')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === \App\Enums\StatusPenerimaanPo::Sebagian->value ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Sebagian
                    </button>
                    <button
                        wire:click="$set('filterStatus', '{{ \App\Enums\StatusPenerimaanPo::Ditolak->value }}')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === \App\Enums\StatusPenerimaanPo::Ditolak->value ? 'bg-white dark:bg-zinc-900 text-red-600 dark:text-red-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Ditolak
                    </button>
                @endif
            </div>

            {{-- Search Bar --}}
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ $activeTab === 'tracking' ? 'Cari kode PO atau nama vendor...' : 'Cari kode RCV, surat jalan, atau kode PO...' }}"
                    icon="magnifying-glass"
                    clearable
                />
            </div>
        </div>

        {{-- Tabel Konten Berdasarkan Tab --}}
        @if ($activeTab === 'tracking')
            {{-- TAB 1: TABEL TRACKING PO --}}
            <flux:table bleed container:class="mt-2">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode PO</flux:table.column>
                    <flux:table.column>Vendor</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_po'" :direction="$sortDirection" wire:click="sort('tanggal_po')">Tanggal PO</flux:table.column>
                    <flux:table.column>Est. Kirim</flux:table.column>
                    <flux:table.column class="text-right">Total Biaya</flux:table.column>
                    <flux:table.column class="text-center w-40">Status PO</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($items as $item)
                        @php
                            $isLate = $item->estimasi_tanggal_kirim
                                && $item->estimasi_tanggal_kirim->isPast()
                                && $item->status !== \App\Enums\StatusPesananPembelian::DiterimaLengkap->value;
                        @endphp
                        <flux:table.row :key="'po-' . $item->id">
                            <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->pemasok?->nama ?? '-' }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs">{{ $item->tanggal_po?->format('d/m/Y') ?? '-' }}</flux:table.cell>
                            <flux:table.cell class="text-xs">
                                <span class="{{ $isLate ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                    {{ $item->estimasi_tanggal_kirim?->format('d/m/Y') ?? '-' }}
                                </span>
                                @if ($isLate)
                                    <flux:badge color="red" size="sm" class="ml-1">TERLAMBAT</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                                <x-rupiah :value="$item->total_biaya" class="font-semibold text-zinc-900 dark:text-zinc-100" />
                            </flux:table.cell>
                            <flux:table.cell class="text-center">
                                <x-status-badge :status="$item->status" />
                            </flux:table.cell>
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetailPo({{ $item->id }})" icon="eye">
                                        Lihat Detail PO
                                    </flux:menu.item>
                                    @can('create_gudang_stok::penerimaan::po')
                                        @if (in_array($item->status, \App\Enums\StatusPesananPembelian::bisaDiterima()))
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="terima({{ $item->id }})" icon="inbox-arrow-down" class="text-emerald-600 dark:text-emerald-400">
                                                Terima Kiriman PO
                                            </flux:menu.item>
                                        @endif
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Tidak ada pesanan pembelian aktif yang menunggu penerimaan." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            {{-- TAB 2: TABEL RIWAYAT PENERIMAAN RCV --}}
            <flux:table bleed container:class="mt-2">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode RCV</flux:table.column>
                    <flux:table.column>Ref PO</flux:table.column>
                    <flux:table.column>Gudang</flux:table.column>
                    <flux:table.column>Surat Jalan</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_terima'" :direction="$sortDirection" wire:click="sort('tanggal_terima')">Tanggal</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                    <flux:table.column class="text-right w-28">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($items as $item)
                        <flux:table.row :key="'rcv-' . $item->id">
                            <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $item->pesananPembelian?->kode ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $item->gudang?->nama ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $item->nomor_surat_jalan }}</flux:table.cell>
                            <flux:table.cell class="text-xs">{{ $item->tanggal_terima?->format('d/m/Y') ?? '-' }}</flux:table.cell>
                            <flux:table.cell class="text-center">
                                <x-status-badge :status="$item->status" />
                            </flux:table.cell>
                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                            Detail RCV
                                        </flux:menu.item>
                                        @can('create_gudang_stok::penerimaan::po')
                                            @if ($item->pesananPembelian && $item->pesananPembelian->status === \App\Enums\StatusPesananPembelian::DiterimaSebagian->value)
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="terima({{ $item->pesanan_pembelian_id }})" icon="plus" class="text-blue-600 dark:text-blue-400">
                                                    Terima Sisa Barang
                                                </flux:menu.item>
                                            @endif
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Belum ada data penerimaan barang." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif

        <x-table-pagination :paginator="$items" />
    </flux:card>

    @include('livewire.pengadaan.partials.penerimaan-po.modal-penerimaan-po')

    @include('livewire.pengadaan.partials.penerimaan-po.modal-reject-kiriman')
    @include('livewire.pengadaan.partials.penerimaan-po.modal-tracking-detail')

    @include('livewire.pengadaan.partials.penerimaan-po.modal-detail-penerimaan')
</div>
