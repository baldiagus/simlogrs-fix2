@use('App\Enums\StatusPermintaanDistribusi')
<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Picking FEFO & Pengeluaran Barang (SPPB)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Alokasi pengeluaran stok otomatis berbasis First Expired, First Out (FEFO) dan penerbitan Surat Perintah Pengeluaran Barang (SPPB).</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Penyaluran SPPB --}}
        <x-distribusi-sppb-tabs active="picking" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Navigasi Tab --}}
        <div class="flex flex-wrap items-center justify-between gap-4 pt-1">
            <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
                <button
                    type="button"
                    wire:click="setTab('siap-picking')"
                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg transition-colors {{ $activeTab === 'siap-picking' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-bold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    <span>Siap Picking</span>
                    @if ($siapPickingCount > 0)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/60 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60">
                            {{ $siapPickingCount }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">
                            0
                        </span>
                    @endif
                </button>
                <button
                    type="button"
                    wire:click="setTab('riwayat-sppb')"
                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg transition-colors {{ $activeTab === 'riwayat-sppb' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-bold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    <span>Riwayat SPPB</span>
                </button>
            </div>

            {{-- Toolbar Filter & Pencarian --}}
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-80">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ $activeTab === 'siap-picking' ? 'Cari kode amprah, unit, barang...' : 'Cari kode SPPB, ref amprah, unit...' }}"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                @if ($activeTab === 'siap-picking')
                    <div class="w-full sm:w-44">
                        <select wire:model.live="filterUrgensi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                            <option value="">Semua Urgensi</option>
                            <option value="REGULER">REGULER</option>
                            <option value="CITO">CITO (Darurat)</option>
                        </select>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tabel Mode 1: Antrean Siap Picking --}}
        @if ($activeTab === 'siap-picking')
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode Amprah</flux:table.column>
                    <flux:table.column class="w-48">Unit</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')" class="w-28">Tanggal</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'urgensi'" :direction="$sortDirection" wire:click="sort('urgensi')" class="text-center w-24">Urgensi</flux:table.column>
                    <flux:table.column class="w-56">Item</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($items as $item)
                        <flux:table.row :key="$item->id">
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                <div>{{ $item->kode }}</div>
                                @if ($item->status === StatusPermintaanDistribusi::DikirimSebagian->value)
                                    <flux:badge size="xs" color="amber" class="mt-0.5">Sisa Terhutang</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell style="white-space: normal;">
                                <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Gudang: {{ $item->gudang?->nama ?? '-' }}</div>
                            </flux:table.cell>

                            <flux:table.cell class="text-xs tabular-nums">
                                {{ $item->tanggal_permintaan?->format('d/m/Y') ?? '-' }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <x-urgency-badge :urgency="$item->urgensi" />
                            </flux:table.cell>

                            <flux:table.cell style="white-space: normal;">
                                <div class="text-xs">
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $item->detail_count ?? $item->detail->count() }} item</span>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 line-clamp-1" title="{{ $item->detail->map(fn ($d) => ($d->barang?->nama ?? 'Barang') . ' (' . ($d->jumlah_disetujui ?: $d->jumlah) . ' ' . ($d->barang?->satuan?->simbol ?? $d->barang?->satuan?->nama ?? 'unit') . ')')->implode(', ') }}">
                                        {{ $item->detail->map(fn ($d) => ($d->barang?->nama ?? 'Barang') . ' (' . ($d->jumlah_disetujui ?: $d->jumlah) . ' ' . ($d->barang?->satuan?->simbol ?? $d->barang?->satuan?->nama ?? 'unit') . ')')->implode(', ') }}
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="showPermintaanDetail({{ $item->id }})" icon="eye">
                                            Detail Permintaan
                                        </flux:menu.item>

                                        @canany(['create_gudang_stok::picking::distribusi'])
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="confirmPicking({{ $item->id }})" icon="cube" class="text-emerald-600 dark:text-emerald-400">
                                                {{ $item->status === StatusPermintaanDistribusi::DikirimSebagian->value ? 'Picking Sisa Hutang' : 'Eksekusi Picking FEFO' }}
                                            </flux:menu.item>
                                        @endcanany
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Tidak ada antrean amprah yang siap dipicking." :cols="6" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            {{-- Tabel Mode 2: Riwayat SPPB Terbit --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode SPPB</flux:table.column>
                    <flux:table.column>Ref Amprah</flux:table.column>
                    <flux:table.column>Unit Tujuan</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_kirim'" :direction="$sortDirection" wire:click="sort('tanggal_kirim')">Tanggal Kirim</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($items as $item)
                        <flux:table.row :key="$item->id">
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->kode }}
                            </flux:table.cell>

                            <flux:table.cell class="font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                {{ $item->permintaanDistribusi?->kode ?? '-' }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Gudang: {{ $item->gudang?->nama ?? '-' }}</div>
                            </flux:table.cell>

                            <flux:table.cell class="text-xs tabular-nums">
                                {{ $item->tanggal_kirim?->format('d/m/Y') ?? '-' }}
                            </flux:table.cell>

                            <flux:table.cell class="text-center">
                                <x-status-badge :status="$item->status" />
                            </flux:table.cell>

                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="showSppbDetail({{ $item->id }})" icon="eye">
                                            Detail Alokasi Batch
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Belum ada riwayat SPPB yang diterbitkan." :cols="6" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif

        {{-- Pagination --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    @include('livewire.distribusi.partials.picking-distribusi.modal-detail-batch')

    @include('livewire.distribusi.partials.picking-distribusi.modal-detail-permintaan')

    {{-- Modal Konfirmasi Aksi --}}
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
