<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Permintaan Pengadaan (PR)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Kelola permintaan pengadaan barang dari seluruh unit rumah sakit.</flux:subheading>
            </div>

            @can('create_pengadaan::permintaan::pengadaan')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Tambah PR
                </flux:button>
            @endcan
        </div>

        {{-- Navigasi Sub-Tab Hub Pengadaan --}}
        <x-pengadaan-transaksi-tabs active="pr" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode PR atau nama unit..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="{{ \App\Enums\StatusPermintaanPengadaan::Diajukan->value }}">{{ \App\Enums\StatusPermintaanPengadaan::Diajukan->value }}</option>
                    <option value="{{ \App\Enums\StatusPermintaanPengadaan::DiteruskanKePengadaan->value }}">DITERUSKAN KE PENGADAAN</option>
                    <option value="{{ \App\Enums\StatusPermintaanPengadaan::Diproses->value }}">DIPROSES (PO)</option>
                    <option value="{{ \App\Enums\StatusPermintaanPengadaan::Selesai->value }}">SELESAI</option>
                    <option value="{{ \App\Enums\StatusPermintaanPengadaan::Ditolak->value }}">{{ \App\Enums\StatusPermintaanPengadaan::Ditolak->value }}</option>
                </select>
            </div>

            <div class="w-full sm:w-40">
                <select wire:model.live="filterUrgensi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Urgensi</option>
                    <option value="REGULER">REGULER</option>
                    <option value="MENDESAK">MENDESAK</option>
                    <option value="CITO">CITO</option>
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

        {{-- Tabel --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode PR</flux:table.column>
                <flux:table.column class="w-56">Pemohon (Unit / Gudang)</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')" class="w-28">Tanggal</flux:table.column>
                <flux:table.column class="w-28 text-center">Urgensi</flux:table.column>
                <flux:table.column class="text-center w-24">Jumlah Item</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarPr as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell style="white-space: normal;">
                            <button type="button" wire:click="showDetail({{ $item->id }})" class="text-left font-medium text-xs text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-2">
                                {{ $item->nama_pemohon }}
                            </button>
                            <div class="text-[10px] text-zinc-400 mt-0.5 font-mono flex items-center gap-1">
                                @if ($item->unit_id)
                                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold">[Unit]</span>
                                @elseif ($item->gudang_id)
                                    <span class="text-blue-600 dark:text-blue-400 font-semibold">[Gudang]</span>
                                @endif
                                <span>&bull; {{ $item->pengaju?->nama ?? '-' }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs tabular-nums">{{ $item->tanggal_permintaan?->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <x-urgency-badge :urgency="$item->urgensi" />
                        </flux:table.cell>
                        <flux:table.cell class="text-center font-mono text-xs">{{ $item->detail->count() }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-48">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Lihat Detail PR
                                    </flux:menu.item>

                                    @php
                                        $canApproveThis = $this->canUserApprovePr($item);
                                        $approverRoleLabel = $item->gudang_id ? 'Kagud' : ($item->unit?->kode === 'IPSRS' ? 'Ka. IPSRS' : 'Karu');
                                    @endphp

                                    {{-- MEJA 1: Otorisasi Kepala Ruangan / Unit / Gudang (Khusus status DIAJUKAN) --}}
                                    @can('update_pengadaan::permintaan::pengadaan')
                                        @if ($item->status === \App\Enums\StatusPermintaanPengadaan::Diajukan->value && $canApproveThis)
                                            <flux:menu.item wire:click="confirmApprove({{ $item->id }})" icon="check-circle" class="text-emerald-600 dark:text-emerald-400">
                                                Setujui ({{ $approverRoleLabel }})
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="confirmReject({{ $item->id }})" icon="x-circle" variant="danger">
                                                Tolak ({{ $approverRoleLabel }})
                                            </flux:menu.item>
                                        @endif
                                    @endcan

                                    {{-- MEJA 2: Meja Bagian Pengadaan (Khusus status DITERUSKAN_KE_PENGADAAN) --}}
                                    @can('create_pengadaan::pesanan::pembelian')
                                        @if ($item->status === \App\Enums\StatusPermintaanPengadaan::DiteruskanKePengadaan->value)
                                            <flux:menu.item
                                                href="/{{ \App\Support\Portal\PortalResolver::resolve() }}/pesanan-pembelian?konversi_pr={{ $item->id }}"
                                                icon="shopping-cart"
                                            >
                                                Terbitkan PO
                                            </flux:menu.item>
                                            <flux:menu.item
                                                wire:click="openRejectPengadaan({{ $item->id }})"
                                                icon="x-circle"
                                                variant="danger"
                                            >
                                                Tolak di Pengadaan
                                            </flux:menu.item>
                                        @endif
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada permintaan pengadaan atau sesuai kriteria pencarian." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Pagination --}}
        <x-table-pagination :paginator="$daftarPr" />
    </flux:card>

    @include('livewire.pengadaan.partials.permintaan-pengadaan.modal-pr-form')

    @include('livewire.pengadaan.partials.permintaan-pengadaan.modal-pr-detail')

    @include('livewire.pengadaan.partials.permintaan-pengadaan.modal-reject-pengadaan')
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
