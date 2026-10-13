@use('App\Enums\Distribusi\StatusPermintaanDistribusi')
@use('App\Enums\Distribusi\StatusPengirimanBarang')
<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">{{ $pageTitle }}</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Verifikasi dan otorisasi permintaan distribusi barang dari ruangan sebelum diproses gudang logistik.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Modul Amprah Ruangan --}}
        <x-tabs.distribusi-amprah active="approval-karu" />
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
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status</option>
                    <option value="{{ StatusPermintaanDistribusi::Diajukan->value }}">DIAJUKAN (Menunggu Karu)</option>
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
        </div>

        {{-- Tabel Permintaan Distribusi --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode</flux:table.column>
                <flux:table.column>Unit</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')">Tanggal</flux:table.column>
                <flux:table.column>Urgensi</flux:table.column>
                <flux:table.column class="text-center">Item</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-40">Status</flux:table.column>
                <flux:table.column class="text-right w-36">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarPermintaan as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode }}
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Gudang: {{ $item->gudang?->nama ?? '-' }}</div>
                        </flux:table.cell>

                        <flux:table.cell class="text-xs tabular-nums">
                            {{ $item->tanggal_permintaan?->format('d/m/Y') ?? '-' }}
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
                            @php
                                $canApproveThis = $canApproveKaru && ($isGlobalAdmin || in_array((int) $item->unit_id, $approvableUnitIds, true));
                            @endphp
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Periksa Dokumen
                                    </flux:menu.item>

                                    @if ($item->status === StatusPermintaanDistribusi::Diajukan->value && $canApproveThis)
                                        <flux:menu.item wire:click="confirmApproveKaru({{ $item->id }})" icon="check" class="text-emerald-600 dark:text-emerald-400">
                                            Setujui Amprah
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmRejectKaru({{ $item->id }})" icon="x-mark" variant="danger">
                                            Tolak Amprah
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-ui.empty-state message="Belum ada permintaan amprah yang memerlukan otorisasi." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Navigasi Paginasi --}}
        <x-ui.table-pagination :paginator="$daftarPermintaan" />
    </flux:card>

    {{-- Modal Detail Permintaan Distribusi --}}
    <flux:modal name="modal-detail-permintaan" wire:model="showDetailModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPermintaan)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Permintaan Amprah</flux:heading>
                            <span class="font-mono text-sm font-bold text-zinc-500 dark:text-zinc-400">#{{ $selectedPermintaan->kode }}</span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal Permintaan: {{ $selectedPermintaan->tanggal_permintaan?->format('d F Y') }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2" style="margin-right: 2.25rem;">
                        <x-ui.urgency-badge :urgency="$selectedPermintaan->urgensi" />
                        <x-ui.status-badge :status="$selectedPermintaan->status" />
                    </div>
                </div>

                {{-- Informasi Metadata --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Pemohon</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->unit?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Gudang Tujuan</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Diajukan Oleh</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->pengaju?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Disetujui Oleh</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPermintaan->penyetuju?->nama ?? 'Belum disetujui' }}</div>
                    </div>
                </div>

                @if ($selectedPermintaan->catatan)
                    <div>
                        <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">Catatan Permintaan</div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg">
                            {{ $selectedPermintaan->catatan }}
                        </div>
                    </div>
                @endif

                @if ($selectedPermintaan->status === StatusPermintaanDistribusi::DikirimSebagian->value)
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-200 flex items-center gap-2">
                        <flux:icon name="clock" class="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <span>Permintaan ini telah dikirim sebagian. Sisa barang yang terhutang akan otomatis diprioritaskan saat gudang menerima restok barang dari vendor.</span>
                    </div>
                @endif

                {{-- Tabel Detail Barang --}}
                <div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-2">Daftar Komoditas / Barang</div>
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                                <tr>
                                    <th class="text-left px-3 py-2 w-10">#</th>
                                    <th class="text-left px-3 py-2">Barang</th>
                                    <th class="text-left px-3 py-2 w-20">Satuan</th>
                                    <th class="text-right px-3 py-2 w-20">Diminta</th>
                                    <th class="text-right px-3 py-2 w-20">Disetujui</th>
                                    <th class="text-right px-3 py-2 w-20">Terkirim</th>
                                    <th class="text-right px-3 py-2 w-28">Sisa Hutang</th>
                                    <th class="text-left px-3 py-2">Catatan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @php
                                    $shipmentDetails = $selectedPermintaan->pengiriman
                                        ? $selectedPermintaan->pengiriman->where('status', '!=', StatusPengirimanBarang::Batal->value)->flatMap->detail
                                        : collect();
                                @endphp
                                @forelse ($selectedPermintaan->detail as $detail)
                                    @php
                                        $terkirim = (int) $shipmentDetails->where('barang_id', $detail->barang_id)->sum('jumlah_dikirim');
                                        $disetujui = (int) ($detail->jumlah_disetujui !== null ? $detail->jumlah_disetujui : $detail->jumlah);
                                        $sisaHutang = max(0, $disetujui - $terkirim);
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 text-zinc-500 text-xs">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $detail->barang?->kode ?? '' }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->barang?->satuan?->nama ?? $detail->barang?->satuan?->simbol ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ number_format($detail->jumlah, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums text-xs">
                                            @if ($detail->jumlah_disetujui !== null)
                                                <span class="text-emerald-600 font-semibold">{{ number_format($detail->jumlah_disetujui, 0, ',', '.') }}</span>
                                            @else
                                                <span class="text-zinc-500 dark:text-zinc-400 italic text-xs font-sans">Belum diproses</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums text-xs text-zinc-700 dark:text-zinc-300">
                                            {{ number_format($terkirim, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono tabular-nums text-xs">
                                            @if ($selectedPermintaan->status === StatusPermintaanDistribusi::Diajukan->value || $detail->jumlah_disetujui === null)
                                                <span class="text-zinc-400 italic text-xs font-sans">-</span>
                                            @elseif ($sisaHutang > 0)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                    {{ number_format($sisaHutang, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">0 (Lengkap)</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs text-zinc-500">
                                            {{ $detail->catatan ?: '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-3 py-4 text-center text-xs text-zinc-500 dark:text-zinc-400">
                                            Tidak ada item barang dalam permintaan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @php
                    $canApproveSelected = $canApproveKaru && ($isGlobalAdmin || in_array((int) $selectedPermintaan->unit_id, $approvableUnitIds, true));
                @endphp

                @if ($selectedPermintaan->status === StatusPermintaanDistribusi::Diajukan->value && $canApproveSelected)
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button
                            wire:click="confirmRejectKaru({{ $selectedPermintaan->id }})"
                            variant="danger"
                            icon="x-circle"
                            wire:loading.attr="disabled"
                        >
                            Tolak Amprah
                        </flux:button>
                        <flux:button
                            wire:click="confirmApproveKaru({{ $selectedPermintaan->id }})"
                            variant="primary"
                            icon="check-circle"
                            wire:loading.attr="disabled"
                        >
                            Setujui Amprah
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>

    {{-- Modal Konfirmasi Aksi --}}
    <x-ui.confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
