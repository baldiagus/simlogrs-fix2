    {{-- Modal Formulir Lapor Kerusakan --}}
    <flux:modal name="modal-lapor-servis" wire:model="showModal" :dismissible="false" :closable="false" class="w-full max-w-3xl">
        <form wire:submit="save" class="space-y-3.5">
            <div class="pb-2.5 border-b border-zinc-200 dark:border-zinc-800">
                <flux:heading size="lg">Formulir Lapor Kerusakan Sarana</flux:heading>
                <flux:subheading>Sampaikan keluhan kendala teknis fasilitas atau alat kerja ruangan untuk ditangani tim IPSRS.</flux:subheading>
            </div>
            {{-- Tahap 1: Identifikasi Sarana & Tingkat Urgensi --}}
            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-2.5">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Identifikasi Sarana & Tingkat Urgensi Kerusakan
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    <div>
                        <x-ui.searchable-select
                            model="aset_id"
                            label="Pilih Fasilitas / Alat yang Bermasalah"
                            placeholder="-- Cari Inventaris Aset... --"
                            :options="$daftarAsetTersedia"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Tingkat Urgensi Kerusakan <span class="text-red-500">*</span>
                        </label>
                        <select
                            wire:model="prioritas"
                            required
                            class="w-full text-sm rounded-lg border {{ $errors->has('prioritas') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                        >
                            <option value="SEDANG">SEDANG (Operasional umum)</option>
                            <option value="RENDAH">RENDAH (Masalah minor)</option>
                            <option value="TINGGI">TINGGI (Pelayanan terganggu)</option>
                            <option value="CITO">CITO (Darurat: Pelayanan kritis)</option>
                        </select>
                        @error('prioritas') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Tahap 2: Rincian Gejala Kerusakan & Bukti Foto Fisik --}}
            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-2.5">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 2: Rincian Gejala Kerusakan & Bukti Foto Fisik
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 items-start">
                    {{-- Kolom Kiri: Deskripsi Masalah --}}
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Deskripsi Masalah / Gejala Kerusakan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            wire:model="deskripsi_masalah"
                            required
                            rows="4"
                            placeholder="Jelaskan secara jelas gejala kerusakan fisik, suara aneh, kebocoran, atau tombol yang tidak berfungsi..."
                            class="w-full text-sm rounded-lg border {{ $errors->has('deskripsi_masalah') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none resize-none"
                        ></textarea>
                        @error('deskripsi_masalah') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Kolom Kanan: Foto Bukti Kerusakan --}}
                    <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Foto Bukti Kerusakan (Opsional)
                    </label>

                    @if ($foto)
                        <div class="relative inline-block border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden p-1.5 bg-zinc-50 dark:bg-zinc-800">
                            <img src="{{ $foto->temporaryUrl() }}" alt="Pratinjau Foto Kerusakan" class="h-36 w-auto max-w-full rounded-lg object-contain">
                            <button
                                type="button"
                                wire:click="hapusFotoPratinjau"
                                class="absolute top-3 right-3 p-1 rounded-full bg-red-600/90 hover:bg-red-700 text-white shadow-md transition"
                                title="Hapus foto"
                            >
                                <flux:icon name="x-mark" class="size-4" />
                            </button>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 text-center mt-1">
                                Foto siap diunggah & dikompresi otomatis
                            </div>
                        </div>
                    @else
                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-28 border-2 border-zinc-300 dark:border-zinc-700 border-dashed rounded-xl cursor-pointer bg-zinc-50/50 dark:bg-zinc-800/40 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                                <div class="flex flex-col items-center justify-center pt-3 pb-3">
                                    <flux:icon name="camera" class="size-6 text-zinc-400 mb-1" />
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                        <span class="font-semibold text-indigo-600 dark:text-indigo-400">Pilih foto</span> atau ambil dari kamera
                                    </p>
                                    <p class="text-[10px] text-zinc-400">Format JPG, PNG, WEBP (Maksimal 10MB)</p>
                                </div>
                                <input type="file" wire:model="foto" accept="image/jpeg,image/png,image/webp" class="hidden" />
                            </label>
                        </div>
                    @endif

                    <div wire:loading wire:target="foto" class="text-xs text-indigo-600 dark:text-indigo-400 mt-1 flex items-center gap-1.5">
                        <svg class="animate-spin size-3.5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Mengunggah dan memproses pratinjau foto...</span>
                    </div>
                    @error('foto') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                    </div>
                </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Kirim Laporan ke IPSRS</span>
                    <span wire:loading wire:target="save">Mengirim...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
