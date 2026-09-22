<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header & Tombol Terima PO --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Penerimaan Barang (Inbound)</flux:heading>
                <flux:subheading>Catat penerimaan fisik barang dari vendor berdasarkan PO yang dikirim.</flux:subheading>
            </div>

            @can('create_gudang_stok::penerimaan::po')
                <flux:modal.trigger name="modal-pilih-po">
                    <flux:button variant="primary" icon="plus" wire:loading.attr="disabled">
                        Terima PO
                    </flux:button>
                </flux:modal.trigger>
            @endcan
        </div>

        {{-- Filter Status Pills --}}
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
            <button
                wire:click="$set('filterStatus', '')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Semua
            </button>
            <button
                wire:click="$set('filterStatus', 'LENGKAP')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'LENGKAP' ? 'bg-white dark:bg-zinc-900 text-emerald-600 dark:text-emerald-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Lengkap
            </button>
            <button
                wire:click="$set('filterStatus', 'SEBAGIAN')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'SEBAGIAN' ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Sebagian
            </button>
            <button
                wire:click="$set('filterStatus', 'DITOLAK')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'DITOLAK' ? 'bg-white dark:bg-zinc-900 text-red-600 dark:text-red-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Ditolak
            </button>
        </div>

        {{-- Toolbar Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode RCV, surat jalan, atau kode PO..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>
        </div>

        {{-- Tabel --}}
        <flux:table bleed container:class="mt-4">
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
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $item->pesananPembelian?->kode ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $item->gudang?->nama ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $item->nomor_surat_jalan }}</flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $item->tanggal_terima?->format('d/m/Y') ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada data penerimaan barang." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Pilih PO --}}
    <flux:modal name="modal-pilih-po" class="md:w-[36rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Pilih PO untuk Diterima</flux:heading>
                <flux:subheading>Pilih Purchase Order yang barangnya akan diterima.</flux:subheading>
            </div>

            @if ($daftarPo->isEmpty())
                <div class="py-8 text-center text-sm text-zinc-500">
                    Tidak ada PO dengan status DIKIRIM VENDOR atau DITERIMA SEBAGIAN.
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($daftarPo as $poItem)
                        <button
                            type="button"
                            wire:click="terima({{ $poItem->id }})"
                            class="w-full flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition-colors text-left"
                        >
                            <div>
                                <div class="font-mono font-semibold text-sm">{{ $poItem->kode }}</div>
                                <div class="text-xs text-zinc-500">{{ $poItem->pemasok?->nama ?? '-' }} &bull; {{ $poItem->tanggal_po?->format('d/m/Y') }}</div>
                            </div>
                            <x-status-badge :status="$poItem->status" />
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Modal Form Penerimaan --}}
    <flux:modal wire:model="showModal" class="w-full max-w-[95vw] lg:max-w-7xl xl:max-w-[88rem]">
        <form wire:submit="savePenerimaan" class="space-y-4">
            <div>
                <flux:heading size="lg">Penerimaan Barang dari PO</flux:heading>
                <flux:subheading>
                    @if ($po)
                        {{ $po->kode }} &mdash; {{ $po->pemasok?->nama ?? '-' }}
                    @endif
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <flux:input wire:model="kode" label="Kode Penerimaan (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />

                <flux:input wire:model="nomor_surat_jalan" label="Nomor Surat Jalan" placeholder="No. surat jalan vendor" required />

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Gudang Tujuan</label>
                    <select wire:model.live="gudang_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="">Pilih Gudang...</option>
                        @foreach ($daftarGudang as $g)
                            <option value="{{ $g->id }}">{{ $g->nama }}</option>
                        @endforeach
                    </select>
                    @error('gudang_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <flux:textarea wire:model="catatan" label="Catatan (Opsional)" rows="2" placeholder="Catatan penerimaan..." />

            {{-- Items Table --}}
            @if (count($itemRows) > 0)
                <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                    <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Detail Item Penerimaan</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-zinc-500">
                                    <th class="pb-2.5 font-semibold min-w-[180px]">Nama Barang</th>
                                    <th class="pb-2.5 font-semibold text-center w-20">Dipesan</th>
                                    <th class="pb-2.5 font-semibold text-center w-24">Sudah Terima</th>
                                    <th class="pb-2.5 font-semibold text-center w-20">Sisa</th>
                                    <th class="pb-2.5 font-semibold w-24">Jml Terima</th>
                                    <th class="pb-2.5 font-semibold w-24">Jml Rusak</th>
                                    <th class="pb-2.5 font-semibold w-32">No. Batch</th>
                                    <th class="pb-2.5 font-semibold w-36">Kedaluwarsa</th>
                                    <th class="pb-2.5 font-semibold w-40">Rak / Lokasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($itemRows as $idx => $row)
                                    <tr wire:key="inbound-item-{{ $idx }}">
                                        <td class="py-2.5 pr-2">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row['nama_barang'] }}</div>
                                        </td>
                                        <td class="py-2.5 text-center font-mono tabular-nums text-zinc-600 dark:text-zinc-400">{{ number_format($row['jumlah_dipesan'], 0, ',', '.') }}</td>
                                        <td class="py-2.5 text-center font-mono tabular-nums text-zinc-600 dark:text-zinc-400">{{ number_format($row['jumlah_sudah'], 0, ',', '.') }}</td>
                                        <td class="py-2.5 text-center font-mono tabular-nums font-semibold {{ $row['sisa'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ number_format($row['sisa'], 0, ',', '.') }}</td>
                                        <td class="py-2.5 px-1">
                                            <input type="number" wire:model="itemRows.{{ $idx }}.jumlah_diterima" min="0" max="{{ $row['sisa'] }}" class="w-full text-right font-mono tabular-nums text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100" />
                                            @error("itemRows.{$idx}.jumlah_diterima") <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="py-2.5 px-1">
                                            <input type="number" wire:model="itemRows.{{ $idx }}.jumlah_rusak" min="0" class="w-full text-right font-mono tabular-nums text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100" />
                                            @error("itemRows.{$idx}.jumlah_rusak") <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="py-2.5 px-1">
                                            <input type="text" wire:model="itemRows.{{ $idx }}.nomor_batch" placeholder="No. Batch" class="w-full font-mono uppercase text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100" />
                                            @error("itemRows.{$idx}.nomor_batch") <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="py-2.5 px-1">
                                            <input type="date" wire:model="itemRows.{{ $idx }}.tanggal_kedaluwarsa" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100" />
                                        </td>
                                        <td class="py-2.5 px-1">
                                            <select wire:model="itemRows.{{ $idx }}.lokasi_gudang_id" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100">
                                                <option value="">Pilih Rak...</option>
                                                @foreach ($daftarLokasiGudang as $lok)
                                                    <option value="{{ $lok->id }}">{{ $lok->nama ?? $lok->kode }}</option>
                                                @endforeach
                                            </select>
                                            @error("itemRows.{$idx}.lokasi_gudang_id") <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="savePenerimaan">Simpan Penerimaan</span>
                    <span wire:loading wire:target="savePenerimaan">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Detail Penerimaan --}}
    <flux:modal wire:model="showDetailModal" class="w-full max-w-[92vw] lg:max-w-6xl">
        @if ($selectedPenerimaan)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Detail Penerimaan: {{ $selectedPenerimaan->kode }}</flux:heading>
                    <flux:subheading>Ref PO: {{ $selectedPenerimaan->pesananPembelian?->kode ?? '-' }} &mdash; {{ $selectedPenerimaan->pesananPembelian?->pemasok?->nama ?? '-' }}</flux:subheading>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-zinc-500">Gudang</div>
                        <div class="font-medium">{{ $selectedPenerimaan->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Surat Jalan</div>
                        <div class="font-medium">{{ $selectedPenerimaan->nomor_surat_jalan }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Tanggal Terima</div>
                        <div class="font-medium">{{ $selectedPenerimaan->tanggal_terima?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Diterima Oleh</div>
                        <div class="font-medium">{{ $selectedPenerimaan->penerima?->nama ?? '-' }}</div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <x-status-badge :status="$selectedPenerimaan->status" />
                    @if ($selectedPenerimaan->catatan)
                        <span class="text-xs text-zinc-500 italic">{{ $selectedPenerimaan->catatan }}</span>
                    @endif
                </div>

                {{-- Detail Items --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-zinc-500">
                                <th class="pb-2 font-semibold">Barang</th>
                                <th class="pb-2 font-semibold text-center">Diterima</th>
                                <th class="pb-2 font-semibold text-center">Rusak</th>
                                <th class="pb-2 font-semibold text-center">Nett</th>
                                <th class="pb-2 font-semibold">Batch</th>
                                <th class="pb-2 font-semibold">Kedaluwarsa</th>
                                <th class="pb-2 font-semibold">Rak</th>
                                <th class="pb-2 font-semibold text-right">Harga Satuan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($selectedPenerimaan->detail as $det)
                                <tr>
                                    <td class="py-2 pr-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $det->barang?->nama ?? '-' }}</td>
                                    <td class="py-2 text-center font-mono">{{ number_format($det->jumlah_diterima, 0, ',', '.') }}</td>
                                    <td class="py-2 text-center font-mono {{ $det->jumlah_rusak > 0 ? 'text-red-500 font-semibold' : '' }}">{{ number_format($det->jumlah_rusak, 0, ',', '.') }}</td>
                                    <td class="py-2 text-center font-mono font-semibold text-emerald-600">{{ number_format($det->jumlah_diterima - $det->jumlah_rusak, 0, ',', '.') }}</td>
                                    <td class="py-2 font-mono">{{ $det->nomor_batch }}</td>
                                    <td class="py-2">{{ $det->tanggal_kedaluwarsa?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="py-2">{{ $det->lokasiGudang?->nama ?? $det->lokasiGudang?->kode ?? '-' }}</td>
                                    <td class="py-2 text-right"><x-rupiah :value="$det->harga_satuan" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
