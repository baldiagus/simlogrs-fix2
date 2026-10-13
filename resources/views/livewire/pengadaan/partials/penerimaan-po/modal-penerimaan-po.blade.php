    {{-- Modal Form Penerimaan PO --}}
    <flux:modal name="modal-penerimaan-po" wire:model="showModal" class="w-full max-w-5xl" style="max-height: 88vh; overflow-y: auto;">
        <form wire:submit="savePenerimaan" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4" style="padding-right: 2.5rem;">
                <div>
                    <flux:heading size="lg">Penerimaan Barang dari PO</flux:heading>
                    <flux:subheading>
                        @if ($po)
                            PO: <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $po->kode }}</span> - {{ $po->pemasok?->nama ?? '-' }}
                        @endif
                    </flux:subheading>
                </div>
                <div class="text-left sm:text-right">
                    <div class="text-[10px] font-medium text-zinc-700 dark:text-zinc-300">No. Penerimaan</div>
                    <div class="font-mono text-sm font-semibold text-zinc-900 dark:text-zinc-100 bg-zinc-100 dark:bg-zinc-800/80 px-2 py-0.5 rounded mt-0.5 border border-zinc-200 dark:border-zinc-700">
                        {{ $kode ?: 'OTOMATIS' }}
                    </div>
                </div>
            </div>

            {{-- Tahap 1: Dokumen Surat Jalan & Gudang Simpan --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Verifikasi Dokumen Surat Jalan & Fasilitas Gudang Simpan
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Nomor Surat Jalan <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model="nomor_surat_jalan"
                            placeholder="No. surat jalan vendor..."
                            required
                            class="w-full text-sm rounded-lg border {{ $errors->has('nomor_surat_jalan') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm"
                        />
                        @error('nomor_surat_jalan') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-ui.searchable-select
                            model="gudang_id"
                            label="Gudang Simpan"
                            placeholder="Pilih Fasilitas Gudang..."
                            :options="$daftarGudang"
                            live="true"
                            required
                        />
                    </div>
                </div>

                @php
                    $adaBarangRusak = collect($itemRows)->sum(fn($r) => (int)($r['jumlah_rusak'] ?? 0)) > 0;
                @endphp
                <div>
                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Catatan Penerimaan 
                        @if ($adaBarangRusak)
                            <span class="text-red-500 font-semibold">* (Wajib diisi berita acara/alasan kerusakan)</span>
                        @else
                            <span class="text-zinc-500 dark:text-zinc-400 font-normal">(Opsional)</span>
                        @endif
                    </label>
                    <textarea
                        wire:model="catatan"
                        rows="2"
                        placeholder="{{ $adaBarangRusak ? 'Jelaskan kondisi kerusakan fisik (pecah, segel robek, basah, dll) untuk bukti klaim retur vendor...' : 'misal: Kemasan kardus utuh, nomor segel vendor sesuai...' }}"
                        class="w-full text-sm rounded-lg border {{ $errors->has('catatan') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm resize-none"
                    ></textarea>
                    @error('catatan') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Tahap 2: Pemeriksaan Fisik Barang & Alokasi Rak --}}
            @if (count($itemRows) > 0)
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-700 text-xs">
                        <div class="font-semibold text-zinc-700 dark:text-zinc-300">
                            Tahap 2: Pemeriksaan Fisik Barang, Nomor Batch & Alokasi Rak ({{ count($itemRows) }} Item)
                        </div>
                        <div class="text-zinc-500 dark:text-zinc-400 text-[11px]">
                            Verifikasi data PO &bull; Catat fisik masuk & rak simpan
                        </div>
                    </div>

                    <div class="space-y-3 pb-2">
                        @foreach ($itemRows as $idx => $row)
                            <div wire:key="inbound-item-card-{{ $idx }}" class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm relative" style="overflow: visible; z-index: {{ 30 - $idx }};">
                                {{-- Baris 1: Ringkasan Pesanan (PO) --}}
                                <div class="px-3 py-2.5 bg-zinc-50/80 dark:bg-zinc-800/40 border-b border-zinc-200/80 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-t-xl">
                                    <div class="flex items-center gap-2.5">
                                        <div class="size-5 rounded-full bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 flex items-center justify-center text-[10px] font-bold shrink-0">
                                            {{ $idx + 1 }}
                                        </div>
                                        <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $row['nama_barang'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-zinc-500">Sisa:</span>
                                            <span class="font-semibold px-2 py-0.5 rounded {{ $row['sisa'] > 0 ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/80 dark:text-amber-200' : 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-200' }}">
                                                {{ number_format($row['sisa'], 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                        <div class="text-zinc-500">Pesan: <span class="font-medium text-zinc-700 dark:text-zinc-300 tabular-nums">{{ number_format($row['jumlah_dipesan'], 0, ',', '.') }}</span></div>
                                        <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                        <div class="text-zinc-500">Terima: <span class="font-medium text-zinc-700 dark:text-zinc-300 tabular-nums">{{ number_format($row['jumlah_sudah'], 0, ',', '.') }}</span></div>
                                    </div>
                                </div>

                                {{-- Baris 2: Input Fisik Penerimaan --}}
                                @if ($row['sisa'] <= 0)
                                    <div class="p-3 bg-zinc-50/60 dark:bg-zinc-800/30 text-center text-xs text-emerald-700 dark:text-emerald-400 font-medium flex items-center justify-center gap-1.5">
                                        <flux:icon icon="check-circle" class="size-4 text-emerald-600" />
                                        <span>Komoditas ini sudah terpenuhi lengkap ({{ number_format($row['jumlah_sudah'], 0, ',', '.') }}) pada penerimaan sebelumnya.</span>
                                    </div>
                                @else
                                    <div class="p-3 bg-white dark:bg-zinc-900">
                                        <div class="grid grid-cols-2 gap-3 items-start">
                                            <div class="flex flex-col">
                                                <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                                                    Jml Terima <span class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="number"
                                                    wire:model="itemRows.{{ $idx }}.jumlah_diterima"
                                                    min="0"
                                                    max="{{ $row['sisa'] }}"
                                                    placeholder="Maks: {{ $row['sisa'] }}"
                                                    class="w-full text-right font-mono tabular-nums text-sm rounded-md border {{ $errors->has("itemRows.{$idx}.jumlah_diterima") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-shadow"
                                                />
                                                @error("itemRows.{$idx}.jumlah_diterima") <span class="text-red-500 text-[10px] mt-0.5 leading-tight font-medium">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="flex flex-col">
                                                <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                                                    Jml Rusak
                                                </label>
                                                <input
                                                    type="number"
                                                    wire:model="itemRows.{{ $idx }}.jumlah_rusak"
                                                    min="0"
                                                    max="{{ $row['jumlah_dipesan'] }}"
                                                    placeholder="Maks: {{ $row['jumlah_dipesan'] }}"
                                                    x-on:input="if (parseInt($el.value) > {{ (int) $row['jumlah_dipesan'] }}) $el.value = {{ (int) $row['jumlah_dipesan'] }}; if (parseInt($el.value) < 0) $el.value = 0;"
                                                    class="w-full text-right font-mono tabular-nums text-sm rounded-md border {{ $errors->has("itemRows.{$idx}.jumlah_rusak") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-shadow"
                                                />
                                                @error("itemRows.{$idx}.jumlah_rusak") <span class="text-red-500 text-[10px] mt-0.5 leading-tight">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="flex flex-col">
                                                <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                                                    No. Batch <span class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    wire:model="itemRows.{{ $idx }}.nomor_batch"
                                                    placeholder="Batch pabrik..."
                                                    class="w-full font-mono uppercase text-sm rounded-md border {{ $errors->has("itemRows.{$idx}.nomor_batch") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-shadow"
                                                />
                                                @error("itemRows.{$idx}.nomor_batch") <span class="text-red-500 text-[10px] mt-0.5 leading-tight font-medium">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="flex flex-col">
                                                <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                                                    Kedaluwarsa (ED)
                                                </label>
                                                <input
                                                    type="date"
                                                    wire:model="itemRows.{{ $idx }}.tanggal_kedaluwarsa"
                                                    class="w-full text-sm rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-shadow text-zinc-600 dark:text-zinc-400"
                                                />
                                            </div>

                                            <div class="col-span-2" wire:key="rak-select-wrapper-{{ $gudang_id ?? 'empty' }}-{{ $idx }}">
                                                <x-ui.searchable-select
                                                    model="itemRows.{{ $idx }}.lokasi_gudang_id"
                                                    label="Rak Simpan (Opsional)"
                                                    placeholder="Pilih Rak (Opsional)..."
                                                    :options="$daftarLokasiGudang"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button
                    type="button"
                    variant="danger"
                    icon="no-symbol"
                    wire:click="openRejectModal"
                >
                    Tolak Kiriman Vendor
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="savePenerimaan">Simpan Penerimaan</span>
                    <span wire:loading wire:target="savePenerimaan">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
