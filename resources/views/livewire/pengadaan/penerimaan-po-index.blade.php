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
    <flux:modal wire:model="showModal" class="w-full max-w-5xl">
        <form wire:submit="savePenerimaan" class="space-y-4">
            <div>
                <flux:heading size="lg">Penerimaan Barang dari PO</flux:heading>
                <flux:subheading>
                    @if ($po)
                        {{ $po->kode }} &mdash; {{ $po->pemasok?->nama ?? '-' }}
                    @endif
                </flux:subheading>
            </div>

            {{-- Dokumen Header Input (Aligned baselines) --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5 flex items-center justify-between">
                        <span>No. Penerimaan</span>
                        <span class="text-[10px] text-zinc-400 font-mono font-normal">Otomatis</span>
                    </label>
                    <input
                        type="text"
                        wire:model="kode"
                        disabled
                        readonly
                        class="w-full text-sm font-mono font-semibold rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800/80 px-3 py-2 text-zinc-600 dark:text-zinc-300 cursor-not-allowed"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Nomor Surat Jalan <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="nomor_surat_jalan"
                        placeholder="No. surat jalan vendor..."
                        required
                        class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                    @error('nomor_surat_jalan') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Gudang Simpan <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model.live="gudang_id"
                        required
                        class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                        <option value="">Pilih Fasilitas Gudang...</option>
                        @foreach ($daftarGudang as $g)
                            <option value="{{ $g->id }}">{{ $g->nama }}</option>
                        @endforeach
                    </select>
                    @error('gudang_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                    Catatan Penerimaan <span class="text-zinc-400 font-normal">(Opsional)</span>
                </label>
                <input
                    type="text"
                    wire:model="catatan"
                    placeholder="misal: Kemasan kardus utuh, nomor segel sesuai..."
                    class="w-full text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>

            {{-- Items Card List --}}
            @if (count($itemRows) > 0)
                <div class="space-y-2.5 pt-1">
                    <div class="flex items-center justify-between pb-1 border-b border-zinc-200 dark:border-zinc-800 text-xs">
                        <div class="font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">
                            Daftar Komoditas Diterima ({{ count($itemRows) }} Item)
                        </div>
                        <div class="text-zinc-400 text-[11px]">
                            Verifikasi data PO &bull; Catat fisik masuk & rak simpan
                        </div>
                    </div>

                    <div class="space-y-2.5 max-h-[55vh] overflow-y-auto pr-1">
                        @foreach ($itemRows as $idx => $row)
                            <div wire:key="inbound-item-card-{{ $idx }}" class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden shadow-xs">
                                {{-- Baris 1: Ringkasan Pesanan (PO) --}}
                                <div class="px-4 py-2 bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200/80 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <span class="size-5 rounded-full bg-blue-600 text-white flex items-center justify-center text-[11px] font-bold shrink-0">
                                            {{ $idx + 1 }}
                                        </span>
                                        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $row['nama_barang'] }}</span>
                                    </div>

                                    <div class="flex items-center gap-3 font-mono tabular-nums text-xs">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-zinc-500 font-sans text-[11px]">Dipesan:</span>
                                            <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ number_format($row['jumlah_dipesan'], 0, ',', '.') }}</span>
                                        </div>
                                        <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-zinc-500 font-sans text-[11px]">Sudah Terima:</span>
                                            <span class="font-bold text-zinc-600 dark:text-zinc-400">{{ number_format($row['jumlah_sudah'], 0, ',', '.') }}</span>
                                        </div>
                                        <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-zinc-500 font-sans text-[11px]">Sisa:</span>
                                            <span class="font-bold px-2.5 py-0.5 rounded-full inline-block {{ $row['sisa'] > 0 ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/80 dark:text-amber-200' : 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-200' }}">
                                                {{ number_format($row['sisa'], 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Baris 2: Input Fisik Penerimaan (5 Kolom Sejajar) --}}
                                <div class="p-3 bg-white dark:bg-zinc-900">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                                                Jml Terima <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                wire:model="itemRows.{{ $idx }}.jumlah_diterima"
                                                min="0"
                                                max="{{ $row['sisa'] }}"
                                                placeholder="Maks: {{ $row['sisa'] }}"
                                                class="w-full text-right font-mono tabular-nums text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                            @error("itemRows.{$idx}.jumlah_diterima") <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                                                Jml Rusak
                                            </label>
                                            <input
                                                type="number"
                                                wire:model="itemRows.{{ $idx }}.jumlah_rusak"
                                                min="0"
                                                placeholder="0"
                                                class="w-full text-right font-mono tabular-nums text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                            @error("itemRows.{$idx}.jumlah_rusak") <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                                                No. Batch <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                wire:model="itemRows.{{ $idx }}.nomor_batch"
                                                placeholder="Batch pabrik..."
                                                class="w-full font-mono uppercase text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                            @error("itemRows.{$idx}.nomor_batch") <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                                                Kedaluwarsa (ED)
                                            </label>
                                            <input
                                                type="date"
                                                wire:model="itemRows.{{ $idx }}.tanggal_kedaluwarsa"
                                                class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                                                Rak Simpan <span class="text-red-500">*</span>
                                            </label>
                                            <select
                                                wire:model="itemRows.{{ $idx }}.lokasi_gudang_id"
                                                class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            >
                                                <option value="">Pilih Rak...</option>
                                                @foreach ($daftarLokasiGudang as $lok)
                                                    <option value="{{ $lok->id }}">{{ $lok->nama ?? $lok->kode }}</option>
                                                @endforeach
                                            </select>
                                            @error("itemRows.{$idx}.lokasi_gudang_id") <p class="text-red-500 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
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
