<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Tracking PO Vendor</flux:heading>
                <flux:subheading>Pantau status pengiriman pesanan pembelian yang sedang aktif.</flux:subheading>
            </div>
        </div>

        {{-- Filter Status (Pills) --}}
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
            <button
                wire:click="$set('filterStatus', '')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Semua
            </button>
            <button
                wire:click="$set('filterStatus', 'DITERBITKAN')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'DITERBITKAN' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Diterbitkan
            </button>
            <button
                wire:click="$set('filterStatus', 'DIKIRIM_VENDOR')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'DIKIRIM_VENDOR' ? 'bg-white dark:bg-zinc-900 text-indigo-600 dark:text-indigo-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Dikirim Vendor
            </button>
            <button
                wire:click="$set('filterStatus', 'DITERIMA_SEBAGIAN')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'DITERIMA_SEBAGIAN' ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Diterima Sebagian
            </button>
        </div>

        {{-- Search Bar --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode PO atau nama vendor..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>
        </div>

        {{-- Tabel --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode PO</flux:table.column>
                <flux:table.column>Vendor</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_po'" :direction="$sortDirection" wire:click="sort('tanggal_po')">Tanggal PO</flux:table.column>
                <flux:table.column>Est. Kirim</flux:table.column>
                <flux:table.column class="text-right">Total Biaya</flux:table.column>
                <flux:table.column class="text-center w-40">Status</flux:table.column>
                <flux:table.column class="text-right w-20">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    @php
                        $isLate = $item->estimasi_tanggal_kirim
                            && $item->estimasi_tanggal_kirim->isPast()
                            && $item->status !== 'DITERIMA_LENGKAP';
                    @endphp
                    <flux:table.row :key="$item->id">
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
                        <flux:table.cell class="text-right">
                            <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Tidak ada pesanan pembelian aktif yang perlu dilacak." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Detail PO --}}
    <flux:modal name="modal-tracking-detail" wire:model="showDetailModal" class="md:w-[48rem]">
        @if ($selectedPo)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Detail PO: {{ $selectedPo->kode }}</flux:heading>
                    <flux:subheading>Informasi lengkap pesanan pembelian dan status pengiriman.</flux:subheading>
                </div>

                {{-- Info Ringkas --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 text-xs">
                    <div>
                        <div class="text-zinc-400 font-medium">Vendor</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPo->pemasok?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-400 font-medium">Tanggal PO</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPo->tanggal_po?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-400 font-medium">Est. Kirim</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPo->estimasi_tanggal_kirim?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-400 font-medium">Status</div>
                        <div><x-status-badge :status="$selectedPo->status" /></div>
                    </div>
                </div>

                @php
                    $isLate = $selectedPo->estimasi_tanggal_kirim
                        && $selectedPo->estimasi_tanggal_kirim->isPast()
                        && $selectedPo->status !== 'DITERIMA_LENGKAP';
                @endphp
                @if ($isLate)
                    <div class="flex items-center gap-2 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
                        <flux:icon.exclamation-triangle class="size-5" />
                        <span class="font-semibold">Pengiriman terlambat — estimasi kirim {{ $selectedPo->estimasi_tanggal_kirim->format('d/m/Y') }} sudah terlewat.</span>
                    </div>
                @endif

                {{-- Daftar Item --}}
                <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Daftar Item</div>
                <flux:table bleed>
                    <flux:table.columns>
                        <flux:table.column>Barang</flux:table.column>
                        <flux:table.column class="text-right">Qty Pesan</flux:table.column>
                        <flux:table.column class="text-right">Qty Diterima</flux:table.column>
                        <flux:table.column class="text-right">Harga Satuan</flux:table.column>
                        <flux:table.column class="text-right">Subtotal</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($selectedPo->detail as $detail)
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                    <div class="text-[11px] text-zinc-400 font-mono">{{ $detail->barang?->kode ?? '' }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="text-right font-mono text-xs tabular-nums">{{ number_format($detail->jumlah_dipesan, 0, ',', '.') }}</flux:table.cell>
                                <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                                    @if ($detail->jumlah_diterima >= $detail->jumlah_dipesan)
                                        <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ number_format($detail->jumlah_diterima, 0, ',', '.') }}</span>
                                    @else
                                        <span class="{{ $detail->jumlah_diterima > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-400' }}">{{ number_format($detail->jumlah_diterima, 0, ',', '.') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                                    <x-rupiah :value="$detail->harga_satuan" />
                                </flux:table.cell>
                                <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                                    <x-rupiah :value="$detail->subtotal" class="font-semibold" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-empty-state message="Tidak ada item dalam PO ini." cols="5" />
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                {{-- Total --}}
                <div class="flex justify-end p-3 rounded-lg bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800">
                    <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                        Total: <x-rupiah :value="$selectedPo->total_biaya" class="text-blue-600 dark:text-blue-400" />
                    </div>
                </div>

                {{-- Timeline Penerimaan --}}
                @if ($selectedPo->penerimaanBarang->isNotEmpty())
                    <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Riwayat Penerimaan</div>
                    <div class="space-y-2">
                        @foreach ($selectedPo->penerimaanBarang as $penerimaan)
                            <div class="flex items-start gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 text-xs">
                                <flux:icon.inbox-arrow-down class="size-4 text-emerald-500 mt-0.5 shrink-0" />
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $penerimaan->kode ?? 'Penerimaan' }}</div>
                                    <div class="text-zinc-400">{{ $penerimaan->tanggal_terima?->format('d/m/Y H:i') ?? '-' }}</div>
                                    <div class="text-zinc-500 mt-0.5"><x-status-badge :status="$penerimaan->status" /></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
