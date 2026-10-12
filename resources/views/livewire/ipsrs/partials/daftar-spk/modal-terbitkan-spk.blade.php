    {{-- Modal Disposisi / Terbitkan SPK --}}
    <flux:modal name="modal-terbitkan-spk" wire:model="showSpkModal" class="md:w-[44rem]">
        @if ($selectedTiketForSpk)
            <form wire:submit="saveSpk" class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Disposisi Surat Perintah Kerja (SPK)</flux:heading>
                    <flux:subheading>Tugaskan teknisi pelaksana dan target penyelesaian perbaikan fisik sarana.</flux:subheading>
                </div>

                {{-- Tahap 1: Verifikasi Tiket & Bukti Kerusakan Fisik --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 1: Verifikasi Tiket & Bukti Kerusakan Fisik
                    </div>
                    <div class="p-3 rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/60 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div>
                            <div class="text-zinc-500 dark:text-zinc-400">No. Tiket</div>
                            <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiketForSpk->kode }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-500 dark:text-zinc-400">Sarana / Alat</div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiketForSpk->aset?->barang?->nama ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-500 dark:text-zinc-400">Unit Pelapor</div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedTiketForSpk->unit?->nama ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-500 dark:text-zinc-400">Urgensi</div>
                            <div class="font-bold text-rose-600 dark:text-rose-400 mt-0.5">{{ $selectedTiketForSpk->prioritas }}</div>
                        </div>
                    </div>
                    @if ($selectedTiketForSpk->foto_kerusakan || $selectedTiketForSpk->deskripsi_masalah)
                        <div class="p-3 rounded-lg bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 text-xs space-y-2">
                            <div>
                                <span class="font-semibold text-zinc-700 dark:text-zinc-300">Deskripsi Keluhan:</span>
                                <span class="text-zinc-800 dark:text-zinc-200 ml-1">{{ $selectedTiketForSpk->deskripsi_masalah }}</span>
                            </div>
                            @if ($selectedTiketForSpk->foto_kerusakan)
                                <div class="flex items-center gap-3 pt-2 border-t border-zinc-200/60 dark:border-zinc-700/60">
                                    <a href="{{ asset('storage/' . ltrim($selectedTiketForSpk->foto_kerusakan, '/')) }}" target="_blank" class="block rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden hover:opacity-90 shadow-xs">
                                        <img src="{{ asset('storage/' . ltrim($selectedTiketForSpk->foto_kerusakan, '/')) }}"
                                             onerror="this.onerror=null; this.src='/storage/{{ ltrim($selectedTiketForSpk->foto_kerusakan, '/') }}';"
                                             alt="Bukti Kerusakan" class="h-20 w-auto max-w-xs object-cover rounded" />
                                    </a>
                                    <div class="space-y-1">
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Bukti visual kondisi fisik dari pelapor.</div>
                                        <a href="{{ asset('storage/' . ltrim($selectedTiketForSpk->foto_kerusakan, '/')) }}" target="_blank" class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1 font-semibold">
                                            <flux:icon name="magnifying-glass-plus" class="size-3.5" />
                                            Lihat Foto Resolusi Penuh
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-2 pt-2 border-t border-zinc-200/60 dark:border-zinc-700/60 text-zinc-400 text-xs italic">
                                    <flux:icon name="photo" class="size-4 text-zinc-400" />
                                    <span>Tidak ada foto bukti kerusakan fisik yang dilampirkan oleh pelapor.</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
                {{-- Tahap 2: Penugasan Teknisi & Target Waktu --}}
                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 2: Penugasan Teknisi & Target Waktu Pengerjaan
                    </div>
                    <x-searchable-select
                        model="teknisi_id"
                        label="Teknisi Pelaksana Ditugaskan"
                        placeholder="-- Cari & Pilih Teknisi IPSRS... --"
                        :options="$daftarTeknisi"
                        required
                    />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Tanggal Penugasan <span class="text-red-500">*</span></flux:label>
                            <flux:input type="date" wire:model="tanggal_penugasan" required />
                            <flux:error name="tanggal_penugasan" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Target Selesai</flux:label>
                            <flux:input type="date" wire:model="target_selesai" />
                            <flux:error name="target_selesai" />
                        </flux:field>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Instruksi Khusus Koordinator (Opsional)
                        </label>
                        <textarea
                            wire:model="instruksi_khusus"
                            rows="2"
                            placeholder="Contoh: Periksa kelistrikan panel MCB terlebih dahulu, koordinasikan dengan perawat jaga..."
                            class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        ></textarea>
                        @error('instruksi_khusus') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showSpkModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveSpk">Terbitkan SPK</span>
                        <span wire:loading wire:target="saveSpk">Menerbitkan...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
