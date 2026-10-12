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
        <x-sirkulasi-peminjaman-tabs active="approval" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Toolbar filter dan pencarian --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pt-1">
            <div class="flex flex-wrap items-center gap-1.5">
                <flux:button
                    wire:click="$set('filterStatus', '')"
                    size="xs"
                    :variant="$filterStatus === '' ? 'primary' : 'ghost'"
                >
                    Semua
                </flux:button>
                <flux:button
                    wire:click="$set('filterStatus', '{{ \App\Enums\StatusPeminjaman::Diajukan->value }}')"
                    size="xs"
                    :variant="$filterStatus === \App\Enums\StatusPeminjaman::Diajukan->value ? 'primary' : 'ghost'"
                >
                    Menunggu Otorisasi
                    @if ($countMenunggu > 0)
                        <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/60 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60">
                            {{ $countMenunggu }}
                        </span>
                    @endif
                </flux:button>
                <flux:button
                    wire:click="$set('filterStatus', '{{ \App\Enums\StatusPeminjaman::Disetujui->value }}')"
                    size="xs"
                    :variant="$filterStatus === \App\Enums\StatusPeminjaman::Disetujui->value ? 'primary' : 'ghost'"
                >
                    Siap Serah Terima
                    @if ($countSiapSerahTerima > 0)
                        <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-900 dark:bg-blue-900/60 dark:text-blue-200 border border-blue-300 dark:border-blue-700/60">
                            {{ $countSiapSerahTerima }}
                        </span>
                    @endif
                </flux:button>
                <flux:button
                    wire:click="$set('filterStatus', '{{ \App\Enums\StatusPeminjaman::Dipinjam->value }}')"
                    size="xs"
                    :variant="$filterStatus === \App\Enums\StatusPeminjaman::Dipinjam->value ? 'primary' : 'ghost'"
                >
                    Sedang Dipinjam
                    @if ($countDipinjam > 0)
                        <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-900 dark:bg-emerald-900/60 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700/60">
                            {{ $countDipinjam }}
                        </span>
                    @endif
                </flux:button>
            </div>

            <div class="flex items-center gap-2.5 w-full md:w-auto">
                <div class="w-full sm:w-72">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari kode PINJ, aset, peminjam..."
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                <div class="w-40 shrink-0">
                    <select
                        wire:model.live="filterStatus"
                        class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100"
                    >
                        <option value="">Semua Status</option>
                        @foreach (\App\Enums\StatusPeminjaman::cases() as $statusPinjam)
                            <option value="{{ $statusPinjam->value }}">{{ $statusPinjam->value }}</option>
                        @endforeach
                    </select>
                </div>
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
                        $user = auth()->user();
                        $canManageItem = $isGlobalAdmin ||
                            ($item->tipe_penyedia === 'RUANGAN' && (int) $user?->unit_id === (int) $item->unit_penyedia_id) ||
                            ($item->tipe_penyedia === 'GUDANG' && (int) $user?->gudang_id === (int) $item->gudang_penyedia_id);
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
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Detail Permintaan
                                    </flux:menu.item>
                                    @if ($item->status === \App\Enums\StatusPeminjaman::Diajukan->value && $canManageItem)
                                        <flux:menu.item wire:click="confirmApprove({{ $item->id }})" icon="check-circle" class="text-emerald-600 dark:text-emerald-400">
                                            Setujui
                                        </flux:menu.item>
                                        <flux:menu.item wire:click="confirmReject({{ $item->id }})" icon="x-circle" variant="danger">
                                            Tolak
                                        </flux:menu.item>
                                    @endif
                                    @if ($item->status === \App\Enums\StatusPeminjaman::Disetujui->value && $canManageItem)
                                        <flux:menu.item wire:click="confirmSerahTerima({{ $item->id }})" icon="arrow-right-start-on-rectangle">
                                            Serah Terima
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Tidak ada permohonan peminjaman aset yang sesuai filter." cols="6" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$daftarPermintaan" />
    </flux:card>

    {{-- Modal detail peminjaman aset --}}
    <flux:modal wire:model="showDetailModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPinjam)
            <div class="space-y-6">
                <div>
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">Rincian Permintaan Peminjaman</flux:heading>
                        <div style="margin-right: 2.25rem;">
                            <x-status-badge :status="$selectedPinjam->status" />
                        </div>
                    </div>
                    <flux:subheading class="mt-1 font-mono text-xs text-zinc-500">
                        {{ $selectedPinjam->kode }}
                    </flux:subheading>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Peminjam</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->unitPeminjam?->nama ?? '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Penyedia Aset</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            @if ($selectedPinjam->tipe_penyedia === 'GUDANG')
                                Gudang: {{ $selectedPinjam->gudangPenyedia?->nama ?? '-' }}
                            @else
                                Ruangan: {{ $selectedPinjam->unitPenyedia?->nama ?? '-' }}
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Diajukan Oleh</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->pengaju?->nama ?? '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Tanggal Pinjam</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->tanggal_pinjam?->format('d/m/Y') ?? '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Rencana Kembali</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Diverifikasi Oleh</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedPinjam->penyetuju?->nama ?? '-' }}
                        </div>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <div class="text-zinc-500 dark:text-zinc-400">Keperluan</div>
                        <div class="text-zinc-800 dark:text-zinc-200 mt-0.5">
                            {{ $selectedPinjam->keperluan ?: '-' }}
                        </div>
                    </div>

                    @if ($selectedPinjam->catatan)
                        <div class="sm:col-span-2 lg:col-span-3">
                            <div class="text-zinc-500 dark:text-zinc-400">Catatan Tambahan</div>
                            <div class="text-zinc-800 dark:text-zinc-200 mt-0.5">
                                {{ $selectedPinjam->catatan }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Tabel daftar aset yang dipinjam --}}
                <div class="space-y-2">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Daftar Aset yang Diminta
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800 text-left text-xs">
                            <thead class="bg-zinc-50 dark:bg-zinc-900 text-zinc-500">
                                <tr>
                                    <th class="px-3 py-2.5 font-medium">No</th>
                                    <th class="px-3 py-2.5 font-medium">Kode Register Aset</th>
                                    <th class="px-3 py-2.5 font-medium">Nama Barang / Aset</th>
                                    <th class="px-3 py-2.5 font-medium">Nomor Seri</th>
                                    <th class="px-3 py-2.5 font-medium">Kondisi Saat Pinjam</th>
                                    <th class="px-3 py-2.5 font-medium">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-950">
                                @forelse ($selectedPinjam->detail as $idx => $detail)
                                    <tr>
                                        <td class="px-3 py-2.5 text-zinc-400">{{ $idx + 1 }}</td>
                                        <td class="px-3 py-2.5 font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $detail->aset?->kode_aset ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2.5 text-zinc-800 dark:text-zinc-200 font-medium">
                                            {{ $detail->aset?->barang?->nama ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-zinc-500 dark:text-zinc-400">
                                            {{ $detail->aset?->nomor_seri ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                                {{ $detail->kondisi_saat_pinjam ?? $detail->aset?->kondisi ?? 'BAIK' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->catatan ?: '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-4 text-center text-zinc-400">
                                            Tidak ada aset terdaftar pada permintaan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @php
                    $user = auth()->user();
                    $canManageSelected = $isGlobalAdmin ||
                        ($selectedPinjam->tipe_penyedia === 'RUANGAN' && (int) $user?->unit_id === (int) $selectedPinjam->unit_penyedia_id) ||
                        ($selectedPinjam->tipe_penyedia === 'GUDANG' && (int) $user?->gudang_id === (int) $selectedPinjam->gudang_penyedia_id);
                @endphp

                @if ($canManageSelected)
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        @if ($selectedPinjam->status === \App\Enums\StatusPeminjaman::Diajukan->value)
                            <flux:button
                                wire:click="confirmReject({{ $selectedPinjam->id }})"
                                variant="danger"
                                icon="x-circle"
                                wire:loading.attr="disabled"
                            >
                                Tolak Permintaan
                            </flux:button>
                            <flux:button
                                wire:click="confirmApprove({{ $selectedPinjam->id }})"
                                variant="primary"
                                icon="check-circle"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white"
                                wire:loading.attr="disabled"
                            >
                                Setujui Permintaan
                            </flux:button>
                        @elseif ($selectedPinjam->status === \App\Enums\StatusPeminjaman::Disetujui->value)
                            <flux:button
                                wire:click="confirmSerahTerima({{ $selectedPinjam->id }})"
                                variant="primary"
                                icon="arrow-right-start-on-rectangle"
                                class="bg-blue-600 hover:bg-blue-700 text-white"
                                wire:loading.attr="disabled"
                            >
                                Serah Terima Fisik
                            </flux:button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>

    {{-- Modal konfirmasi tindakan --}}
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
