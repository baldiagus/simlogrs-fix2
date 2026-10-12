<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Stock Opname Fisik (Blind Count)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Pencocokan saldo buku sistem terhadap perhitungan fisik lapangan di depan rak guna menjamin akurasi persediaan 100%.</flux:subheading>
            </div>

            @can('create_gudang_stok::stok::opname')
                <flux:button wire:click="openInisiasi" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Buka Jadwal Opname
                </flux:button>
            @endcan
        </div>

        {{-- Navigasi Sub-Tab Hub Opname & Penyesuaian --}}
        <x-persediaan-opname-tabs active="opname" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode opname, nama gudang..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="flex items-center gap-2.5">
                <div class="w-48">
                    <select wire:model.live="filterGudangId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Fasilitas Gudang</option>
                        @foreach ($daftarGudang as $gdg)
                            <option value="{{ $gdg->id }}">{{ $gdg->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-44">
                    <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">Semua Status</option>
                        <option value="{{ \App\Enums\StatusStokOpname::Berjalan->value }}">BERJALAN (Hitung Fisik)</option>
                        <option value="{{ \App\Enums\StatusStokOpname::MenungguApproval->value }}">MENUNGGU APPROVAL</option>
                        <option value="{{ \App\Enums\StatusStokOpname::Disetujui->value }}">DISETUJUI (Selesai)</option>
                        <option value="{{ \App\Enums\StatusStokOpname::Ditolak->value }}">DITOLAK</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tabel Sesi Stock Opname --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-32">Kode Opname</flux:table.column>
                <flux:table.column class="w-48">Fasilitas Gudang</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_opname'" :direction="$sortDirection" wire:click="sort('tanggal_opname')" class="w-28">Tanggal</flux:table.column>
                <flux:table.column class="text-center w-24">Item Batch</flux:table.column>
                <flux:table.column class="w-36">Pembuat Sesi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                <flux:table.column class="text-right w-36">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $opn)
                    <flux:table.row :key="$opn->id">
                        {{-- Kode Opname --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $opn->kode }}
                        </flux:table.cell>

                        {{-- Fasilitas Gudang --}}
                        <flux:table.cell style="white-space: normal;">
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $opn->gudang?->nama ?? '-' }}
                            </div>
                            <div class="text-[10px] text-zinc-500 font-mono">
                                {{ $opn->gudang?->kode }}
                            </div>
                        </flux:table.cell>

                        {{-- Tanggal --}}
                        <flux:table.cell class="text-xs tabular-nums text-zinc-700 dark:text-zinc-300">
                            {{ $opn->tanggal_opname?->format('d/m/Y') }}
                        </flux:table.cell>

                        {{-- Jumlah Item --}}
                        <flux:table.cell class="text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                                {{ $opn->detail_count }} batch
                            </span>
                        </flux:table.cell>

                        {{-- Pembuat --}}
                        <flux:table.cell style="white-space: normal;">
                            <div class="text-xs text-zinc-800 dark:text-zinc-200">
                                {{ $opn->pembuat?->nama ?? '-' }}
                            </div>
                            @if ($opn->penyesuaian)
                                <div class="text-[10px] text-amber-600 dark:text-amber-400 font-mono mt-0.5">
                                    Ref ADJ: {{ $opn->penyesuaian->kode }}
                                </div>
                            @endif
                        </flux:table.cell>

                        {{-- Status --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$opn->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $opn->id }})" icon="eye">
                                        Rincian Sesi Opname
                                    </flux:menu.item>
                                    @if ($opn->status === \App\Enums\StatusStokOpname::Berjalan->value)
                                        @canany(['update_gudang_stok::stok::opname', 'create_gudang_stok::stok::opname'])
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="openHitungFisik({{ $opn->id }})" icon="clipboard-document-list" class="text-blue-600 dark:text-blue-400">
                                                Input Hitung Fisik
                                            </flux:menu.item>
                                        @endcanany
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada siklus stock opname yang tercatat." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    @include('livewire.persediaan.partials.stock-opname.modal-inisiasi')

    @include('livewire.persediaan.partials.stock-opname.modal-hitung-fisik')

    @include('livewire.persediaan.partials.stock-opname.modal-qr-batch')

    @include('livewire.persediaan.partials.stock-opname.modal-detail-opname')
</div>
