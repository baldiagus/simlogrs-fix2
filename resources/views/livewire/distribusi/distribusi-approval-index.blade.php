@use('App\Enums\Distribusi\StatusPermintaanDistribusi')
@use('App\Enums\Distribusi\StatusPengirimanBarang')
<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Otorisasi Distribusi Logistik (Admin Sentral)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Review amprah ruangan yang telah disetujui Karu dan tentukan kuota persetujuan distribusi berdasarkan stok fisik gudang.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Modul Amprah Ruangan --}}
        <x-tabs.distribusi-amprah active="approval-logistik" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode amprah, unit, atau gudang..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 font-medium">
                    <option value="">Semua Status Terkait</option>
                    <option value="{{ StatusPermintaanDistribusi::DisetujuiKaru->value }}">DISETUJUI KARU (Perlu Review)</option>
                    <option value="{{ StatusPermintaanDistribusi::DisetujuiGudang->value }}">DISETUJUI GUDANG</option>
                    <option value="{{ StatusPermintaanDistribusi::SedangDikirim->value }}">SEDANG DIKIRIM</option>
                    <option value="{{ StatusPermintaanDistribusi::Selesai->value }}">SELESAI</option>
                    <option value="{{ StatusPermintaanDistribusi::Ditolak->value }}">DITOLAK</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterUrgensi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 font-medium">
                    <option value="">Semua Urgensi</option>
                    <option value="REGULER">REGULER</option>
                    <option value="CITO">CITO</option>
                </select>
            </div>
        </div>

        {{-- Tabel Permintaan Distribusi --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-40">Kode Amprah</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'unit_id'" :direction="$sortDirection" wire:click="sort('unit_id')">Unit Pemohon</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')">Tanggal</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'urgensi'" :direction="$sortDirection" wire:click="sort('urgensi')" class="text-center">Urgensi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-64">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                            <div class="text-[11px] text-zinc-500">Gudang: {{ $item->gudang?->nama ?? '-' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs tabular-nums text-zinc-700 dark:text-zinc-300">
                            {{ $item->tanggal_permintaan?->format('d/m/Y') }}
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-ui.urgency-badge :urgency="$item->urgensi" />
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-ui.status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="openDetail({{ $item->id }})" icon="eye">
                                        Periksa Dokumen
                                    </flux:menu.item>

                                    @if ($item->status === StatusPermintaanDistribusi::DisetujuiKaru->value && $canApproveGudang && ($isGlobalAdmin || in_array((int) $item->gudang_id, $approvableGudangIds, true)))
                                        <flux:menu.item wire:click="openReview({{ $item->id }})" icon="clipboard-document-check" class="text-blue-600 dark:text-blue-400">
                                            Review &amp; Setujui
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmRejectGudang({{ $item->id }})" icon="x-mark" variant="danger">
                                            Tolak Permintaan
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-ui.empty-state message="Tidak ada permintaan distribusi yang sesuai dengan filter." :cols="6" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-ui.table-pagination :paginator="$items" />
    </flux:card>

    @include('livewire.distribusi.partials.distribusi-approval.modal-review-kuota')

    @include('livewire.distribusi.partials.distribusi-approval.modal-detail-distribusi')

    {{-- Modal Konfirmasi Aksi --}}
    <x-ui.confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
