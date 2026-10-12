    {{-- Modal Form Tambah Amprah --}}
    <flux:modal name="modal-permintaan-form" wire:model="showModal" class="md:w-[54rem] max-w-[95vw]">
        <form wire:submit="save" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Pengajuan Amprah Ruangan</flux:heading>
                <flux:subheading>Kebutuhan operasional ruangan ke gudang logistik sentral.</flux:subheading>
            </div>

            {{-- Tahap 1: Informasi Dokumen, Lokasi Pemohon & Urgensi --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Informasi Dokumen, Lokasi Pemohon & Urgensi
                </div>

                {{-- Baris 1: Informasi Dokumen & Unit --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            No. Amprah <span class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono font-normal">(Otomatis)</span>
                        </label>
                        <input
                            type="text"
                            wire:model="kode"
                            disabled
                            readonly
                            class="w-full text-sm font-mono font-semibold rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800/80 px-3 py-2 text-zinc-600 dark:text-zinc-300 cursor-not-allowed"
                        />
                    </div>

                    @if ($isGlobalAdmin)
                        <div>
                            <x-searchable-select
                                model="unit_id"
                                label="Unit Pemohon"
                                placeholder="Pilih Unit Ruangan..."
                                :options="$daftarUnit"
                                required
                            />
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Unit Pemohon</label>
                            <input
                                type="text"
                                value="{{ auth()->user()->unit?->nama ?? '-' }}"
                                disabled
                                readonly
                                class="w-full text-sm rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800/80 px-3 py-2 text-zinc-700 dark:text-zinc-300 font-medium cursor-not-allowed"
                            />
                        </div>
                    @endif

                    <div>
                        <x-searchable-select
                            model="gudang_id"
                            label="Gudang Tujuan"
                            placeholder="Pilih Gudang..."
                            :options="$daftarGudang"
                            live="true"
                            required
                        />
                    </div>
                </div>

                {{-- Schedule and notes --}}
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Tanggal Permintaan <span class="text-red-500">*</span>
                        </label>
                        <input type="date" wire:model="tanggal_permintaan" required class="w-full text-sm rounded-lg border {{ $errors->has('tanggal_permintaan') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        @error('tanggal_permintaan') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Urgensi <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="urgensi" required class="w-full text-sm rounded-lg border {{ $errors->has('urgensi') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="REGULER">REGULER</option>
                            <option value="CITO">CITO (Darurat)</option>
                        </select>
                        @error('urgensi') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-6">
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Catatan / Justifikasi <span class="text-zinc-500 dark:text-zinc-400 font-normal">(Opsional)</span>
                        </label>
                        <input
                            type="text"
                            wire:model="catatan"
                            placeholder="Nomor memo atau justifikasi kebutuhan khusus..."
                            class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                </div>
            </div>
            {{-- Requested commodities (1x3 matrix) --}}
            {{-- Tahap 2: Rincian Daftar Komoditas Barang yang Diamprah --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 flex items-center gap-2">
                        <span>Tahap 2: Daftar Komoditas Barang yang Diamprah</span>
                        <span class="text-red-500">*</span>
                    </div>
                    <flux:button type="button" wire:click="addItem" size="xs" variant="ghost" icon="plus">
                        Tambah Baris
                    </flux:button>
                </div>

                {{-- Daftar Baris Barang (Matriks 1x3 per baris) --}}
                <div class="space-y-3">
                    @foreach ($items as $index => $row)
                        <div wire:key="amprah-item-{{ $index }}" class="p-3.5 rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xs">
                            <div class="w-full grid grid-cols-12 gap-3 items-start">
                                {{-- Kolom 1 (1x3): Barang & Informasi Stok Gudang (col-span-5) --}}
                                <div class="col-span-5 w-full min-w-0">
                                    <x-searchable-select
                                        model="items.{{ $index }}.barang_id"
                                        label="Komoditas Barang"
                                        placeholder="Pilih Komoditas Barang..."
                                        :options="$daftarBarang"
                                        live="true"
                                        required
                                    />
                                    {{-- Informasi Stok Gudang Real-Time --}}
                                    @if (!empty($items[$index]['barang_id']))
                                        @php
                                            $bId = (int) $items[$index]['barang_id'];
                                            $selectedB = $daftarBarang->firstWhere('id', $bId);
                                            $stokAda = (int) ($stokGudangMap[$bId] ?? 0);
                                        @endphp
                                        <div class="mt-1.5 flex items-center gap-1.5">
                                            @if ($stokAda > 0)
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                    Stok Gudang: <strong>{{ number_format($stokAda, 0, ',', '.') }} {{ $selectedB?->satuan?->simbol ?? 'Unit' }}</strong>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-medium bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60" title="Kebutuhan komoditas ini akan otomatis dicatat sebagai hutang gudang dan dikirim saat restok.">
                                                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                                                    Stok Gudang 0 (Otomatis Terhutang)
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                    @error("items.{$index}.barang_id") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                {{-- Kolom 2 (1x3): Jumlah Diminta (col-span-2) --}}
                                <div class="col-span-2 w-full min-w-0">
                                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                        Jumlah <span class="text-red-500">*</span>
                                    </label>
                                    @php
                                        $bIdCurrent = (int) ($items[$index]['barang_id'] ?? 0);
                                        $stokMax = $bIdCurrent ? (int) ($stokGudangMap[$bIdCurrent] ?? 0) : 0;
                                    @endphp
                                    <input
                                        type="number"
                                        wire:model="items.{{ $index }}.jumlah"
                                        min="1"
                                        required
                                        placeholder="1"
                                        class="w-full text-sm rounded-lg border {{ $errors->has("items.{$index}.jumlah") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 text-right font-mono tabular-nums focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                    @error("items.{$index}.jumlah") <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                                </div>

                                {{-- Kolom 3 (1x3): Catatan Item & Tombol Hapus (col-span-5) --}}
                                <div class="col-span-5 w-full min-w-0">
                                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                        Catatan Item <span class="text-zinc-500 dark:text-zinc-400 font-normal">(Opsional)</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="text"
                                            wire:model="items.{{ $index }}.catatan"
                                            placeholder="Keterangan peruntukan..."
                                            class="flex-1 text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        />
                                        @if (count($items) > 1)
                                            <flux:button type="button" wire:click="removeItem({{ $index }})" size="sm" variant="ghost" icon="trash" title="Hapus Baris" class="text-red-500 hover:text-red-700 shrink-0" />
                                        @endif
                                    </div>
                                    @error("items.{$index}.catatan") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @error('items') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-xs text-blue-700 dark:text-blue-300 flex items-center gap-2">
                <flux:icon name="information-circle" class="size-4 shrink-0 text-blue-600" />
                <span>Barang yang stok gudangnya kosong atau kurang dari permintaan akan otomatis dicatat sebagai hutang gudang (backorder) dan diterbitkan SPPB susulan saat barang tiba.</span>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Ajukan Amprah Barang</span>
                    <span wire:loading wire:target="save">Memproses...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
