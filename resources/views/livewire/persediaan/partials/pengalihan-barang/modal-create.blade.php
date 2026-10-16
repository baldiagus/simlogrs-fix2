    {{-- MODAL CREATE PENGALIHAN BARANG --}}
    <flux:modal name="modal-create-pengalihan-barang" wire:model="showCreateModal" class="md:w-[54rem] max-w-[95vw]" style="max-height: 90vh; overflow-y: auto;">
        <div class="space-y-6">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Form Pengajuan Pengalihan Barang (BHP)</flux:heading>
                <flux:subheading>Redistribusikan barang persediaan logistik antar-gudang atau ke unit ruangan</flux:subheading>
            </div>

            <form wire:submit="submitPengalihan" class="space-y-5">
                {{-- Tahap 1: Komoditas Barang & Gudang Asal --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 1: Pilih Komoditas & Batch Fisik Asal
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <flux:select wire:model.live="selected_barang_id" label="Komoditas Barang" size="sm" required>
                                <option value="">-- Pilih Komoditas Barang --</option>
                                @foreach ($daftarBarang as $b)
                                    <option value="{{ $b->id }}">{{ $b->nama }} ({{ $b->satuan?->simbol ?? 'Unit' }})</option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div>
                            <flux:select wire:model.live="gudang_asal_id" label="Gudang Asal (Sumber Stok)" size="sm" required>
                                <option value="">-- Pilih Gudang Asal --</option>
                                @foreach ($gudangs as $g)
                                    <option value="{{ $g->id }}" @disabled((int)$gudang_tujuan_id === (int)$g->id)>{{ $g->nama }} @if((int)$gudang_tujuan_id === (int)$g->id) (Tujuan) @endif</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>

                    @if ($selected_barang_id && $gudang_asal_id)
                        <div>
                            <flux:select wire:model.live="selected_stok_batch_id" label="Pilih Batch Stok Fisik Asal" size="sm" required>
                                <option value="">-- Pilih Batch Stok --</option>
                                @foreach ($batchesAsal as $bt)
                                    <option value="{{ $bt->id }}">
                                        Batch: {{ $bt->nomor_batch }} &bull; Sisa: {{ $bt->jumlah }} &bull; ED: {{ $bt->tanggal_kedaluwarsa ?? 'Non-ED' }}
                                    </option>
                                @endforeach
                            </flux:select>
                            @if ($batchesAsal->isEmpty())
                                <p class="text-xs text-amber-600 mt-1">Tidak ada batch stok aktif dengan sisa &gt; 0 di gudang asal ini.</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Tahap 2: Lokasi Tujuan & Jumlah Kuantitas --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 2: Lokasi Tujuan & Jumlah Kuantitas
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5 items-start">
                        {{-- Lokasi Tujuan Penerima (Diperlebar ke 6/12 kolom) --}}
                        <div class="sm:col-span-6">
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Lokasi Tujuan Penerima <span class="text-red-500">*</span>
                            </label>
                            @if ($isDestinationLocked)
                                <div class="p-2.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-medium text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                    <flux:icon name="{{ $tipe_tujuan === 'GUDANG' ? 'building-storefront' : 'building-office' }}" class="size-4 text-blue-600" />
                                    <span>{{ $namaLokasiTujuanUser }}</span>
                                </div>
                            @else
                                <div class="flex items-center gap-3 text-xs mb-2">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="GUDANG" wire:model.live="tipe_tujuan" class="text-blue-600" />
                                        <span>Gudang</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="RUANGAN" wire:model.live="tipe_tujuan" class="text-blue-600" />
                                        <span>Ruangan</span>
                                    </label>
                                </div>

                                @if ($tipe_tujuan === 'GUDANG')
                                    <x-ui.searchable-select
                                        model="gudang_tujuan_id"
                                        placeholder="-- Cari Gudang Logistik Tujuan --"
                                        :options="$gudangs->filter(fn($g) => (int)$gudang_asal_id !== (int)$g->id)->map(fn($g) => ['id' => $g->id, 'nama' => $g->nama, 'sub' => $g->kode])"
                                        live="true"
                                        required
                                    />
                                    @error('gudang_tujuan_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                @else
                                    <x-ui.searchable-select
                                        model="unit_tujuan_id"
                                        placeholder="-- Cari Ruangan / Unit Tujuan --"
                                        :options="$units->map(fn($u) => ['id' => $u->id, 'nama' => $u->nama, 'sub' => $u->kode])"
                                        live="true"
                                        required
                                    />
                                    @error('unit_tujuan_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                @endif
                            @endif
                        </div>

                        {{-- Jumlah Unit (Diringkas ke 3/12 kolom) --}}
                        <div class="sm:col-span-3">
                            <flux:field>
                                <flux:label>Jumlah Unit Dialihkan <span class="text-red-500">*</span></flux:label>
                                <flux:input type="number" min="1" max="{{ $this->maxQuantity }}" wire:model.live="quantity" required />
                                <flux:description class="text-[11px] text-zinc-500 mt-0.5">
                                    Maksimal {{ $this->maxQuantity }} unit tersedia
                                </flux:description>
                                <flux:error name="quantity" />
                            </flux:field>
                        </div>

                        {{-- Tanggal Pengalihan (Diringkas ke 3/12 kolom) --}}
                        <div class="sm:col-span-3">
                            <flux:field>
                                <flux:label>Tanggal Eksekusi <span class="text-red-500">*</span></flux:label>
                                <flux:input type="date" wire:model="tanggal_pengalihan" required />
                                <flux:error name="tanggal_pengalihan" />
                            </flux:field>
                        </div>
                    </div>

                    {{-- Alasan Pengalihan --}}
                    <flux:field>
                        <flux:label>Alasan / Dasar Pengalihan <span class="text-red-500">*</span></flux:label>
                        <flux:textarea
                            wire:model="alasan"
                            placeholder="Jelaskan kebutuhan relokasi persediaan barang ini..."
                            rows="2"
                            required
                        />
                        <flux:error name="alasan" />
                    </flux:field>
                </div>

                {{-- Footer Buttons --}}
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showCreateModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitPengalihan">Ajukan Pengalihan Barang</span>
                        <span wire:loading wire:target="submitPengalihan">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
