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
                    <div class="flex flex-wrap items-center gap-2" style="margin-right: 2.25rem;">
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
                                            class="w-28 text-right font-mono font-bold text-xs rounded-lg border {{ $errors->has("countRows.{$detId}.jumlah_fisik") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-2.5 py-1 text-zinc-900 dark:text-zinc-100 focus:ring-1 focus:ring-blue-500"
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

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="primary" wire:click="saveHitungFisik" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveHitungFisik">Simpan Hasil Hitung Fisik</span>
                        <span wire:loading wire:target="saveHitungFisik">Merekonsiliasi...</span>
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
