<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Stock Opname Fisik (Blind Count)</flux:heading>
                <flux:subheading>Pencocokan saldo buku sistem terhadap perhitungan fisik lapangan di depan rak guna menjamin akurasi persediaan 100%.</flux:subheading>
            </div>

            @can('create_gudang_stok::stok::opname')
                <flux:button wire:click="openInisiasi" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Buka Jadwal Opname
                </flux:button>
            @endcan
        </div>

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
                        <option value="BERJALAN">BERJALAN (Hitung Fisik)</option>
                        <option value="MENUNGGU_APPROVAL">MENUNGGU APPROVAL</option>
                        <option value="DISETUJUI">DISETUJUI (Selesai)</option>
                        <option value="DITOLAK">DITOLAK</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tabel Sesi Stock Opname --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-36">Kode Opname</flux:table.column>
                <flux:table.column>Fasilitas Gudang</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_opname'" :direction="$sortDirection" wire:click="sort('tanggal_opname')" class="w-32">Tanggal</flux:table.column>
                <flux:table.column class="text-center w-28">Item Batch</flux:table.column>
                <flux:table.column>Pembuat Sesi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-36">Status</flux:table.column>
                <flux:table.column class="text-right w-44">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $opn)
                    <flux:table.row :key="$opn->id">
                        {{-- Kode Opname --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $opn->kode }}
                        </flux:table.cell>

                        {{-- Fasilitas Gudang --}}
                        <flux:table.cell>
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
                        <flux:table.cell>
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
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button
                                    wire:click="showDetail({{ $opn->id }})"
                                    size="xs"
                                    variant="ghost"
                                    icon="eye"
                                    title="Lihat Detail Opname"
                                />

                                @if ($opn->status === 'BERJALAN')
                                    @canany(['update_gudang_stok::stok::opname', 'create_gudang_stok::stok::opname', 'page_KepalaGudangDashboard', 'page_Dashboard'])
                                        <flux:button
                                            wire:click="openHitungFisik({{ $opn->id }})"
                                            size="xs"
                                            variant="primary"
                                            icon="clipboard-document-list"
                                            title="Input Perhitungan Fisik Lapangan"
                                        >
                                            Hitung Fisik
                                        </flux:button>
                                    @endcanany
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada siklus stock opname yang tercatat." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Inisiasi Siklus Opname Baru --}}
    <flux:modal name="modal-inisiasi-opname" wire:model="showInisiasiModal" class="md:w-[32rem]">
        <form wire:submit="inisiasiOpname" class="space-y-4">
            <div>
                <flux:heading size="lg">Buka Siklus Stock Opname</flux:heading>
                <flux:subheading>Inisialisasi lembar hitung fisik persediaan untuk fasilitas gudang tertentu.</flux:subheading>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Fasilitas Gudang yang Diopname <span class="text-red-500">*</span>
                </label>
                <select
                    wire:model="gudang_id"
                    required
                    class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <option value="">-- Pilih Fasilitas Gudang --</option>
                    @foreach ($daftarGudang as $gdg)
                        <option value="{{ $gdg->id }}">{{ $gdg->nama }} ({{ $gdg->kode }})</option>
                    @endforeach
                </select>
                @error('gudang_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <flux:field>
                <flux:label>Tanggal Pelaksanaan Opname <span class="text-red-500">*</span></flux:label>
                <flux:input type="date" wire:model="tanggal_opname" required />
                <flux:error name="tanggal_opname" />
            </flux:field>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Catatan Pelaksanaan (Opsional)
                </label>
                <textarea
                    wire:model="catatan"
                    rows="2"
                    placeholder="Contoh: Opname triwulan persediaan alat rumah tangga dan ATK sentral..."
                    class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                ></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showInisiasiModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="inisiasiOpname">Buka Siklus Opname</span>
                    <span wire:loading wire:target="inisiasiOpname">Menyiapkan Batch...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Hitung Fisik Lapangan (Blind Count) --}}
    <flux:modal name="modal-hitung-fisik" wire:model="showCountModal" class="w-full max-w-[95vw] lg:max-w-6xl">
        @if ($activeOpname)
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Hitung Fisik: {{ $activeOpname->kode }}</flux:heading>
                            <span class="text-xs px-2 py-0.5 rounded font-mono bg-blue-50 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 font-bold">BLIND COUNT</span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Gudang: {{ $activeOpname->gudang?->nama }} &bull; Tanggal: {{ $activeOpname->tanggal_opname?->format('d F Y') }}
                        </flux:subheading>
                    </div>

                    {{-- Tombol Ekspor & Impor Excel --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button
                            wire:click="exportExcel({{ $activeOpname->id }})"
                            size="xs"
                            variant="outline"
                            icon="arrow-down-tray"
                        >
                            Unduh Lembar Hitung (Excel)
                        </flux:button>

                        <div class="relative">
                            <input
                                type="file"
                                wire:model="fileExcel"
                                accept=".xlsx,.xls,.csv"
                                id="upload-excel-opname"
                                class="hidden"
                            />
                            <flux:button
                                as="label"
                                for="upload-excel-opname"
                                size="xs"
                                variant="outline"
                                icon="arrow-up-tray"
                                class="cursor-pointer"
                            >
                                <span wire:loading.remove wire:target="fileExcel">Unggah Hasil Excel</span>
                                <span wire:loading wire:target="fileExcel">Mengunggah...</span>
                            </flux:button>
                        </div>

                        @if ($fileExcel)
                            <flux:button wire:click="importExcel" size="xs" variant="primary" class="bg-emerald-600 hover:bg-emerald-700">
                                Proses Impor
                            </flux:button>
                        @endif
                    </div>
                </div>

                <div class="p-3 bg-blue-50/60 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900/40 rounded-xl text-xs text-blue-900 dark:text-blue-200">
                    <strong>Panduan Blind Count:</strong> Hitung kuantitas fisik riil barang yang ada di rak simpan. Kolom saldo sistem disembunyikan demi menjaga objektivitas hasil perhitungan lapangan.
                </div>

                {{-- Tabel Input Fisik --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-xl max-h-[55vh] overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                            <tr>
                                <th class="p-2.5 w-10 text-center">#</th>
                                <th class="p-2.5">Komoditas Barang</th>
                                <th class="p-2.5 w-36">Nomor Batch</th>
                                <th class="p-2.5 w-36">Lokasi Rak</th>
                                <th class="p-2.5 text-center w-20">Satuan</th>
                                <th class="p-2.5 text-right w-36">Jumlah Fisik <span class="text-red-500">*</span></th>
                                <th class="p-2.5 w-52">Catatan Lapangan</th>
                                <th class="p-2.5 text-center w-16">QR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach ($countRows as $detId => $row)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40">
                                    <td class="p-2.5 text-center text-zinc-400 font-mono">{{ $loop->iteration }}</td>
                                    <td class="p-2.5">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row['barang_nama'] }}</div>
                                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $row['barang_kode'] }}</div>
                                    </td>
                                    <td class="p-2.5 font-mono text-zinc-800 dark:text-zinc-200 font-semibold">
                                        {{ $row['nomor_batch'] ?? '-' }}
                                    </td>
                                    <td class="p-2.5 text-zinc-600 dark:text-zinc-400">
                                        {{ $row['lokasi_rak'] }}
                                    </td>
                                    <td class="p-2.5 text-center text-zinc-500">
                                        {{ $row['satuan'] }}
                                    </td>
                                    <td class="p-2.5 text-right">
                                        <input
                                            type="number"
                                            wire:model.lazy="countRows.{{ $detId }}.jumlah_fisik"
                                            min="0"
                                            required
                                            class="w-28 text-right font-mono font-bold text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1 text-zinc-900 dark:text-zinc-100 focus:ring-1 focus:ring-blue-500"
                                        />
                                    </td>
                                    <td class="p-2.5">
                                        <input
                                            type="text"
                                            wire:model.lazy="countRows.{{ $detId }}.catatan"
                                            placeholder="Catatan kondisi fisik..."
                                            class="w-full text-xs rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2 py-1 text-zinc-900 dark:text-zinc-100 focus:ring-1 focus:ring-blue-500"
                                        />
                                    </td>
                                    <td class="p-2.5 text-center">
                                        @if (!empty($activeOpname->detail->find($detId)?->stok_batch_id))
                                            <flux:button
                                                type="button"
                                                wire:click="showQrBatch({{ $activeOpname->detail->find($detId)->stok_batch_id }})"
                                                size="xs"
                                                variant="ghost"
                                                icon="qr-code"
                                                title="Cetak Label QR Batch"
                                            />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showCountModal', false)">
                        Tutup
                    </flux:button>
                    <flux:button type="button" variant="primary" wire:click="saveHitungFisik" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveHitungFisik">Simpan Hasil Hitung Fisik</span>
                        <span wire:loading wire:target="saveHitungFisik">Merekonsiliasi...</span>
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Label QR Code Batch --}}
    <flux:modal name="modal-qr-batch" wire:model="showQrModal" class="md:w-[32rem]">
        @if ($selectedBatchQr)
            <div class="space-y-4 text-center p-2">
                <flux:heading size="lg">Label QR Code Batch Persediaan</flux:heading>
                <flux:subheading>Identitas fisik barcode rak simpan dan batch kedaluwarsa.</flux:subheading>

                <div id="print-area-batch" class="p-6 bg-white dark:bg-zinc-900 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-2xl inline-block shadow-xs">
                    <div class="flex justify-center mb-3">
                        {!! $qrCodeSvg !!}
                    </div>
                    <div class="font-mono font-extrabold text-sm text-zinc-900 dark:text-zinc-100">
                        {{ $selectedBatchQr->nomor_batch }}
                    </div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mt-0.5">
                        {{ $selectedBatchQr->barang?->nama }}
                    </div>
                    <div class="text-[11px] text-zinc-500 font-mono mt-0.5">
                        Rak: {{ $selectedBatchQr->lokasiGudang?->nama ?? 'Sentral' }} &bull; ED: {{ $selectedBatchQr->tanggal_kedaluwarsa?->format('d/m/Y') ?? 'Non-ED' }}
                    </div>
                </div>

                <div class="flex justify-center gap-2 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showQrModal', false)">Tutup</flux:button>
                    <flux:button variant="primary" icon="printer" onclick="window.print()">Cetak Label</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Detail Rekonsiliasi Opname --}}
    <flux:modal name="modal-detail-opname" wire:model="showDetailModal" class="w-full max-w-[95vw] lg:max-w-6xl">
        @if ($selectedOpname)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Rekonsiliasi: {{ $selectedOpname->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Gudang: {{ $selectedOpname->gudang?->nama }} &bull; Tanggal: {{ $selectedOpname->tanggal_opname?->format('d F Y') }}
                        </flux:subheading>
                    </div>
                    <x-status-badge :status="$selectedOpname->status" />
                </div>

                {{-- Tabel Hasil Rekonsiliasi Lengkap --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-xl max-h-[55vh] overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                            <tr>
                                <th class="p-2.5 w-10 text-center">#</th>
                                <th class="p-2.5">Komoditas Barang</th>
                                <th class="p-2.5">Nomor Batch</th>
                                <th class="p-2.5">Lokasi Rak</th>
                                <th class="p-2.5 text-right w-28">Saldo Sistem</th>
                                <th class="p-2.5 text-right w-28">Hitung Fisik</th>
                                <th class="p-2.5 text-right w-28">Selisih</th>
                                <th class="p-2.5">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach ($selectedOpname->detail as $det)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40">
                                    <td class="p-2.5 text-center text-zinc-400 font-mono">{{ $loop->iteration }}</td>
                                    <td class="p-2.5">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $det->barang?->nama }}</div>
                                        <div class="text-[10px] text-zinc-500 font-mono">{{ $det->barang?->kode }}</div>
                                    </td>
                                    <td class="p-2.5 font-mono text-zinc-800 dark:text-zinc-200">
                                        {{ $det->stokBatch?->nomor_batch ?? '-' }}
                                    </td>
                                    <td class="p-2.5 text-zinc-600 dark:text-zinc-400">
                                        {{ $det->stokBatch?->lokasiGudang?->nama ?? 'Tanpa Rak' }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono text-zinc-700 dark:text-zinc-300">
                                        {{ number_format($det->jumlah_sistem, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($det->jumlah_fisik, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-bold {{ $det->selisih < 0 ? 'text-rose-600 dark:text-rose-400' : ($det->selisih > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500') }}">
                                        {{ $det->selisih > 0 ? '+' : '' }}{{ number_format($det->selisih, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-zinc-600 dark:text-zinc-400">
                                        {{ $det->catatan ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
