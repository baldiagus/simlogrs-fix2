    {{-- Modal Pengajuan Peminjaman Baru --}}
    <flux:modal name="modal-pinjam" wire:model="showPinjamModal" class="md:w-[54rem] max-w-[95vw]">
        <div class="space-y-6">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Form Pengajuan Peminjaman Aset</flux:heading>
                <flux:subheading>Pilih fasilitas penyedia aset inventaris yang berstatus tersedia dan dalam kondisi baik.</flux:subheading>
            </div>

            <form wire:submit="savePinjam" class="space-y-5">
                {{-- Tahap 1: Pilih Aset Inventaris --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 1: Pilih Aset Inventaris
                    </div>

                    <div>
                        <x-searchable-select
                            model="selected_barang_id"
                            label="Aset / Komoditas yang Ingin Dipinjam"
                            placeholder="-- Cari Aset Inventaris Tersedia... --"
                            :options="$daftarBarangAsetTersedia->map(fn($b) => ['id' => $b->id, 'nama' => $b->nama, 'sub' => 'Tersedia: ' . $b->aset_count . ' ' . ($b->satuan?->simbol ?? 'Unit')])"
                            live="true"
                            required
                        />

                        @if ($selected_barang_id)
                            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1.5">
                                Ditemukan {{ count($daftarPenyediaAset) }} fasilitas penyedia yang memiliki unit komoditas ini.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Tahap 2: Pilih Unit atau Gudang Penyedia Aset --}}
                @if ($selected_barang_id)
                    <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                            Tahap 2: Pilih Unit Ruangan atau Gudang Penyedia
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                                Lokasi Fasilitas Pemilik Aset <span class="text-red-500">*</span>
                            </label>

                            <div class="mb-2.5">
                                <flux:input
                                    wire:model.live.debounce.200ms="searchPenyedia"
                                    placeholder="Cari nama ruangan atau fasilitas penyedia..."
                                    icon="magnifying-glass"
                                    size="sm"
                                />
                            </div>

                            @if (empty($daftarPenyediaAset))
                                <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300">
                                    Saat ini tidak ada unit ruangan atau fasilitas gudang yang memiliki aset ini dalam kondisi siap pinjam.
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
                                                        Ruangan Anda sendiri (tidak dapat meminjam ke ruangan sendiri)
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

                {{-- Tahap 3: Jadwal dan Keperluan --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 3: Jadwal dan Keperluan Peminjaman
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <flux:field>
                            <flux:label>Jumlah Unit Dipinjam <span class="text-red-500">*</span></flux:label>
                            <flux:input type="number" min="1" max="{{ $this->maxQuantity }}" wire:model.live="quantity" required />
                            <flux:description class="text-[11px] text-zinc-500 mt-0.5">
                                @if ($selected_penyedia_key)
                                    Maksimal {{ $this->maxQuantity }} unit tersedia di fasilitas ini
                                @else
                                    <span class="text-amber-600 dark:text-amber-400">Pilih fasilitas penyedia terlebih dahulu</span>
                                @endif
                            </flux:description>
                            <flux:error name="quantity" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Tanggal Mulai Pinjam <span class="text-red-500">*</span></flux:label>
                            <flux:input type="date" wire:model="tanggal_pinjam" required />
                            <flux:error name="tanggal_pinjam" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Tanggal Rencana Kembali <span class="text-red-500">*</span></flux:label>
                            <flux:input type="date" wire:model="tanggal_kembali_rencana" required />
                            <flux:error name="tanggal_kembali_rencana" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Keperluan Peminjaman <span class="text-red-500">*</span></flux:label>
                        <flux:textarea
                            wire:model="keperluan"
                            placeholder="Jelaskan kebutuhan operasional peminjaman aset ini..."
                            rows="2"
                            required
                        />
                        <flux:error name="keperluan" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Catatan Tambahan (Opsional)</flux:label>
                        <flux:input
                            type="text"
                            wire:model="catatan"
                            placeholder="Contoh: Termasuk kelengkapan kabel power dan remote control"
                        />
                        <flux:error name="catatan" />
                    </flux:field>
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="savePinjam">Kirim Pengajuan</span>
                        <span wire:loading wire:target="savePinjam">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
