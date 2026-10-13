<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">{{ $pageTitle }}</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Verifikasi, otorisasi persetujuan, dan serah terima fisik sirkulasi aset antar unit atau gudang.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Hub Peminjaman --}}
        <x-tabs.sirkulasi-peminjaman active="approval" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Navigasi Tab Status --}}
        <flux:tabs class="pt-2">
            <flux:tab
                wire:click="setFilterTab('')"
                :current="$filterStatus === ''"
                icon="inbox-stack"
                :badge="$countSemua"
            >
                Semua
            </flux:tab>
            <flux:tab
                wire:click="setFilterTab('{{ \App\Enums\Aset\StatusPeminjaman::Diajukan->value }}')"
                :current="$filterStatus === \App\Enums\Aset\StatusPeminjaman::Diajukan->value"
                icon="clock"
                :badge="$countMenunggu"
            >
                Menunggu Otorisasi
            </flux:tab>
            <flux:tab
                wire:click="setFilterTab('{{ \App\Enums\Aset\StatusPeminjaman::Disetujui->value }}')"
                :current="$filterStatus === \App\Enums\Aset\StatusPeminjaman::Disetujui->value"
                icon="check-circle"
                :badge="$countSiapSerahTerima"
            >
                Siap Serah Terima
            </flux:tab>
            <flux:tab
                wire:click="setFilterTab('{{ \App\Enums\Aset\StatusPeminjaman::Dipinjam->value }}')"
                :current="$filterStatus === \App\Enums\Aset\StatusPeminjaman::Dipinjam->value"
                icon="arrow-path"
                :badge="$countDipinjam"
            >
                Sedang Dipinjam
            </flux:tab>
        </flux:tabs>

        {{-- Toolbar pencarian rata kanan --}}
        <div class="flex justify-end pt-1">
            <div class="w-full sm:w-72 md:w-80 ml-auto">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode PINJ, aset, peminjam..."
                    icon="magnifying-glass"
                    size="sm"
                    clearable
                />
            </div>
        </div>
        {{-- Tabel permintaan peminjaman --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode PINJ</flux:table.column>
                <flux:table.column class="w-44">Unit Peminjam</flux:table.column>
                <flux:table.column class="w-48">Aset Diminta</flux:table.column>
                <flux:table.column class="w-40">Periode Pinjam</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarPermintaan as $item)
                    @php
                        $canApproveItem = $izin[$item->id]['approve'] ?? false;
                        $canHandoverItem = $izin[$item->id]['serah_terima'] ?? false;
                    @endphp
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode }}
                        </flux:table.cell>

                        <flux:table.cell style="white-space: normal;">
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->unitPeminjam?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                Pengaju: {{ $item->pengaju?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell style="white-space: normal;">
                            @forelse ($item->detail->take(2) as $det)
                                <div class="text-xs text-zinc-800 dark:text-zinc-200">
                                    {{ $det->aset?->barang?->nama ?? $det->aset?->kode_aset ?? '-' }}
                                </div>
                                <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400">
                                    {{ $det->aset?->kode_aset }}
                                </div>
                            @empty
                                <span class="text-xs text-zinc-400">-</span>
                            @endforelse
                            @if ($item->detail->count() > 2)
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-medium">
                                    +{{ $item->detail->count() - 2 }} aset lainnya
                                </div>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                            <div>{{ $item->tanggal_pinjam?->format('d/m/Y') ?? '-' }} &rarr; {{ $item->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-ui.status-badge :status="$item->status" />
                        </flux:table.cell>

                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Detail Permintaan
                                    </flux:menu.item>
                                    @if ($item->status === \App\Enums\Aset\StatusPeminjaman::Diajukan->value && $canApproveItem)
                                        <flux:menu.item wire:click="confirmApprove({{ $item->id }})" icon="check-circle" class="text-emerald-600 dark:text-emerald-400">
                                            Setujui
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="confirmReject({{ $item->id }})" icon="x-circle" variant="danger">
                                            Tolak
                                        </flux:menu.item>
                                    @endif
                                    @if ($item->status === \App\Enums\Aset\StatusPeminjaman::Disetujui->value && $canHandoverItem)
                                        <flux:menu.item wire:click="confirmSerahTerima({{ $item->id }})" icon="arrow-right-start-on-rectangle">
                                            Serah Terima
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-ui.empty-state message="Tidak ada permohonan peminjaman aset yang sesuai filter." cols="6" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-ui.table-pagination :paginator="$daftarPermintaan" />
    </flux:card>

    @include('livewire.aset.partials.approval-peminjaman.modal-detail')

    {{-- Modal konfirmasi tindakan --}}
    <x-ui.confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
