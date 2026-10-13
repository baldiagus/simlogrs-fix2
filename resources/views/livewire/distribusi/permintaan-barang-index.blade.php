@use('App\Enums\Distribusi\StatusPermintaanDistribusi')
@use('App\Enums\Distribusi\StatusPengirimanBarang')
<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Permintaan Barang Ruangan (Amprah)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Kelola pengajuan permintaan barang dan komoditas dari ruangan ke gudang logistik.</flux:subheading>
            </div>

            @can('create_layanan_unit::permintaan::barang')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Ajukan Amprah
                </flux:button>
            @endcan
        </div>

        {{-- Navigasi Sub-Tab Modul Amprah Ruangan --}}
        <x-tabs.distribusi-amprah active="permintaan" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode amprah atau nama unit..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="{{ StatusPermintaanDistribusi::Diajukan->value }}">DIAJUKAN</option>
                    <option value="{{ StatusPermintaanDistribusi::DisetujuiKaru->value }}">DISETUJUI KARU</option>
                    <option value="{{ StatusPermintaanDistribusi::DisetujuiGudang->value }}">DISETUJUI GUDANG</option>
                    <option value="{{ StatusPermintaanDistribusi::SedangDikirim->value }}">SEDANG DIKIRIM</option>
                    <option value="{{ StatusPermintaanDistribusi::Selesai->value }}">SELESAI</option>
                    <option value="{{ StatusPermintaanDistribusi::Ditolak->value }}">DITOLAK</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterUrgensi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Urgensi</option>
                    <option value="REGULER">REGULER</option>
                    <option value="CITO">CITO (Darurat)</option>
                </select>
            </div>
            @if ($search || $filterStatus || $filterUrgensi)
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

        {{-- Tabel Permintaan Amprah --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode Amprah</flux:table.column>
                <flux:table.column class="w-64">Unit Pemohon & Tujuan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')" class="w-28">Tanggal</flux:table.column>
                <flux:table.column class="w-28 text-center">Urgensi</flux:table.column>
                <flux:table.column class="text-center w-24">Jml Item</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarPermintaan as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <button type="button" wire:click="showDetail({{ $item->id }})" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                {{ $item->kode }}
                            </button>
                        </flux:table.cell>

                        <flux:table.cell style="white-space: normal;">
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Tujuan: {{ $item->gudang?->nama ?? '-' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                            {{ $item->tanggal_permintaan?->format('d/m/Y') }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <x-ui.urgency-badge :urgency="$item->urgensi" />
                        </flux:table.cell>

                        <flux:table.cell class="text-center font-mono text-xs">
                            {{ $item->detail_count ?? $item->detail->count() }}
                        </flux:table.cell>

                        <flux:table.cell class="text-center">
                            <x-ui.status-badge :status="$item->status" />
                        </flux:table.cell>

                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Detail Amprah
                                    </flux:menu.item>
                                    @if (in_array($item->status, StatusPermintaanDistribusi::dapatDibatalkan()) && $canRequesterAct && ($isGlobalAdmin || ($userUnitId && (int) $userUnitId === (int) $item->unit_id)))
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmBatal({{ $item->id }})" icon="x-mark" variant="danger">
                                            Batalkan Permintaan
                                        </flux:menu.item>
                                    @endif
                                    @if ($item->status === StatusPermintaanDistribusi::DikirimSebagian->value && $canRequesterAct && ($isGlobalAdmin || ($userUnitId && (int) $userUnitId === (int) $item->unit_id)))
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmTutupSisaHutang({{ $item->id }})" icon="check-circle" class="text-blue-600">
                                            Tutup Sisa Hutang
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-ui.empty-state message="Belum ada data permintaan amprah ruangan." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Navigasi Paginasi --}}
        <x-ui.table-pagination :paginator="$daftarPermintaan" />
    </flux:card>

    @include('livewire.distribusi.partials.permintaan-barang.modal-form')

    @include('livewire.distribusi.partials.permintaan-barang.modal-detail')

    {{-- Modal Konfirmasi Aksi --}}
    <x-ui.confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
