    {{-- MODAL CREATE PENGALIHAN ASET --}}
    <flux:modal name="modal-create-pengalihan" wire:model="showCreateModal" class="md:w-[54rem] max-w-[95vw]" style="max-height: 90vh; overflow-y: auto;">
        <div class="space-y-6">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Form Pengajuan Pengalihan & Mutasi Aset</flux:heading>
                <flux:subheading>Pilih fasilitas penyedia aset inventaris untuk dialihkan secara permanen ke unit kerja Anda.</flux:subheading>
            </div>

            <form wire:submit="submitPengalihan" class="space-y-5">
                {{-- Tahap 1: Pilih Aset Inventaris --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 1: Pilih Aset Inventaris
                    </div>

                    <div>
                        <x-ui.searchable-select
                            model="selected_barang_id"
                            label="Aset / Komoditas yang Ingin Dialihkan"
                            placeholder="-- Cari Aset Inventaris Tersedia... --"
                            :options="$daftarBarangAsetTersedia->map(fn($b) => ['id' => $b->id, 'nama' => $b->nama, 'sub' => 'Tersedia: ' . $b->aset_count . ' ' . ($b->satuan?->simbol ?? 'Unit')])"
                            live="true"
                            required
                        />

                        @if ($selected_barang_id)
                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1.5">
                                Ditemukan {{ count($daftarPenyediaAset) }} fasilitas pemilik yang memiliki unit komoditas ini.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Tahap 2: Pilih Fasilitas Asal Pemilik Aset --}}
                @if ($selected_barang_id)
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                            Tahap 2: Pilih Lokasi Fasilitas Asal Pemilik Aset (Sumber)
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Lokasi Fasilitas Asal Pemilik Aset <span class="text-red-500">*</span>
                            </label>

                            <div class="mb-2.5">
                                <flux:input
                                    wire:model.live.debounce.200ms="searchPenyedia"
                                    placeholder="Cari nama ruangan atau gudang pemilik..."
                                    icon="magnifying-glass"
                                    size="sm"
                                />
                            </div>

                            @if (empty($daftarPenyediaAset))
                                <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300">
                                    Saat ini tidak ada unit ruangan atau fasilitas gudang yang memiliki aset ini dalam kondisi siap dialihkan.
                                </div>
                            @else
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                    @foreach ($daftarPenyediaAset as $p)
                                        <label
                                            class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors {{ $selected_penyedia_key === $p['key'] ? 'border-blue-600 bg-blue-50/60 dark:bg-blue-900/30 text-blue-950 dark:text-blue-100 ring-1 ring-blue-600' : 'border-zinc-200 dark:border-zinc-700 hover:bg-white dark:hover:bg-zinc-800' }} {{ $p['is_own'] ? 'opacity-60 cursor-not-allowed' : '' }}"
                                        >
                                            <input
                                                type="radio"
                                                wire:model.live="selected_penyedia_key"
                                                value="{{ $p['key'] }}"
                                                class="mt-0.5 text-blue-600 focus:ring-blue-500"
                                                {{ $p['is_own'] ? 'disabled' : '' }}
                                            />
                                            <div class="flex-1 text-xs">
                                                <div class="font-semibold flex items-center justify-between gap-2">
                                                    <span>{{ $p['nama'] }}</span>
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $p['tipe'] === 'RUANGAN' ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' }}">
                                                        {{ $p['tipe'] }}
                                                    </span>
                                                </div>
                                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                    Tersedia: <strong>{{ $p['jumlah'] }} unit</strong> (Kode: {{ $p['kode_asets'] }})
                                                </div>
                                                @if ($p['is_own'])
                                                    <div class="text-xs text-amber-700 dark:text-amber-300 font-medium mt-0.5">
                                                        Fasilitas Anda sendiri (tidak dapat mengalihkan ke diri sendiri)
                                                    </div>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('selected_penyedia_key') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Tahap 3: Lokasi Tujuan & Keperluan Pengalihan (TANPA TANGGAL KEMBALI) --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 3: Lokasi Tujuan & Keperluan Pengalihan
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- Lokasi Tujuan Penerima --}}
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Lokasi Tujuan Penerima (Permanen) <span class="text-red-500">*</span>
                            </label>
                            @if (! $isGlobalAdmin)
                                <div class="p-2.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-medium text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                    <flux:icon name="{{ auth()->user()?->unit_id ? 'building-office' : 'building-storefront' }}" class="size-4 text-blue-600" />
                                    <span>{{ $namaLokasiAsalUser }}</span>
                                </div>
                            @else
                                <div class="flex items-center gap-3 text-xs mb-2">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="RUANGAN" wire:model.live="tipe_tujuan" class="text-blue-600" />
                                        <span>Ruangan / Unit</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="GUDANG" wire:model.live="tipe_tujuan" class="text-blue-600" />
                                        <span>Gudang Logistik</span>
                                    </label>
                                </div>
                                @if ($tipe_tujuan === 'RUANGAN')
                                    <flux:select wire:model.live="unit_tujuan_id" size="sm">
                                        <option value="">-- Pilih Ruangan Tujuan --</option>
                                        @foreach ($units as $u)
                                            <option value="{{ $u->id }}">{{ $u->nama }}</option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:select wire:model.live="gudang_tujuan_id" size="sm">
                                        <option value="">-- Pilih Gudang Tujuan --</option>
                                        @foreach ($gudangs as $g)
                                            <option value="{{ $g->id }}">{{ $g->nama }}</option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            @endif
                        </div>

                        {{-- Jumlah Unit yang Dialihkan --}}
                        <div>
                            <flux:field>
                                <flux:label>Jumlah Unit Dialihkan <span class="text-red-500">*</span></flux:label>
                                <flux:input type="number" min="1" max="{{ $this->maxQuantity }}" wire:model.live="quantity" required />
                                <flux:description class="text-[11px] text-zinc-500 mt-0.5">
                                    @if ($selected_penyedia_key)
                                        Maksimal {{ $this->maxQuantity }} unit tersedia
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">Pilih fasilitas penyedia asal terlebih dahulu</span>
                                    @endif
                                </flux:description>
                                <flux:error name="quantity" />
                            </flux:field>
                        </div>

                        {{-- Tanggal Pengalihan (TANPA TANGGAL KEMBALI) --}}
                        <div>
                            <flux:field>
                                <flux:label>Tanggal Eksekusi Pengalihan <span class="text-red-500">*</span></flux:label>
                                <flux:input type="date" wire:model="tanggal_pengalihan" required />
                                <flux:error name="tanggal_pengalihan" />
                            </flux:field>
                        </div>
                    </div>

                    {{-- Alasan / Dasar Pengalihan --}}
                    <flux:field>
                        <flux:label>Alasan / Dasar Pengalihan Aset <span class="text-red-500">*</span></flux:label>
                        <flux:textarea
                            wire:model="alasan"
                            placeholder="Jelaskan kebutuhan mutasi permanen aset ini (misal: penataan ulang kapasitas ruang perawatan, relokasi alat medis)..."
                            rows="2"
                            required
                        />
                        <flux:error name="alasan" />
                    </flux:field>

                    {{-- Catatan Tambahan (Opsional) --}}
                    <flux:field>
                        <flux:label>Catatan Tambahan (Opsional)</flux:label>
                        <flux:input
                            wire:model="catatan"
                            placeholder="Kelengkapan aksesoris, kabel power, kondisi fisik awal..."
                        />
                    </flux:field>
                </div>

                {{-- Footer Buttons --}}
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showCreateModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitPengalihan">Ajukan Pengalihan Aset</span>
                        <span wire:loading wire:target="submitPengalihan">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
