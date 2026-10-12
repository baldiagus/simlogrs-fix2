    {{-- Modal Form Tambah / Ubah Aset --}}
    <flux:modal name="modal-aset-form" wire:model="showModal" class="md:w-[38rem]">
        <form wire:submit="save" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">{{ $editingId ? 'Ubah Register Aset' : 'Registrasi Unit Aset Baru' }}</flux:heading>
                <flux:subheading>Pencatatan aktiva inventaris, nomor seri, dan lokasi penempatan.</flux:subheading>
            </div>

            {{-- Tahap 1: Komoditas Aset & Penempatan Lokasi Fisik --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Komoditas Aset & Penempatan Lokasi Fisik
                </div>

                <x-searchable-select
                    model="barang_id"
                    label="Komoditas Aset"
                    placeholder="Pilih Barang (Tipe Aset)..."
                    :options="$daftarBarangAset"
                    required
                />

                <div class="p-3.5 rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Penempatan Lokasi Fisik (Ruangan ATAU Gudang)</div>

                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="lokasi_tipe" value="RUANGAN" class="text-blue-600 focus:ring-blue-500">
                            <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">Di Ruangan / Unit Kerja</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model.live="lokasi_tipe" value="GUDANG" class="text-blue-600 focus:ring-blue-500">
                            <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200">Di Fasilitas Gudang</span>
                        </label>
                    </div>

                    @if ($lokasi_tipe === 'RUANGAN')
                        <x-searchable-select
                            model="unit_id"
                            label="Ruangan / Instalasi Dinas"
                            placeholder="Pilih Ruangan..."
                            :options="$daftarUnit"
                            required
                        />
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-searchable-select
                                model="gudang_id"
                                label="Gudang Logistik"
                                placeholder="Pilih Gudang..."
                                :options="$daftarGudang"
                                live="true"
                                required
                            />

                            <x-searchable-select
                                model="lokasi_gudang_id"
                                label="Rak Simpan (Opsional)"
                                placeholder="Tanpa Rak Khusus"
                                :options="$daftarRak"
                            />
                        </div>
                    @endif
                </div>
            </div>
            {{-- Tahap 2: Register Pabrik, Nilai Perolehan & Kondisi --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 2: Register Pabrik, Nilai Perolehan & Kondisi Fisik
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="kode_aset" label="Kode Register Aset (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />
                    <flux:input wire:model="nomor_seri" label="Nomor Seri Pabrik (SN)" placeholder="misal: SN-88492048" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="tanggal_perolehan" label="Tanggal Perolehan" type="date" required />
                    <x-number-input model="nilai_perolehan" label="Nilai Perolehan" prefix="Rp" required />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Kondisi Fisik <span class="text-red-500">*</span></label>
                        <select wire:model="kondisi" required class="w-full text-sm rounded-lg border {{ $errors->has('kondisi') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            <option value="BAIK">BAIK (Siap Operasional)</option>
                            <option value="RUSAK_RINGAN">RUSAK RINGAN</option>
                            <option value="RUSAK_BERAT">RUSAK BERAT</option>
                        </select>
                        @error('kondisi') <p class="mt-1 text-xs text-red-500 flex items-center gap-1"><span class="font-medium">{{ $message }}</span></p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status Aset <span class="text-red-500">*</span></label>
                        <select wire:model="status" required class="w-full text-sm rounded-lg border {{ $errors->has('status') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            @foreach (\App\Enums\StatusAset::dapatDiaturManual() as $statusAset)
                                <option value="{{ $statusAset }}">{{ str_replace('_', ' ', $statusAset) }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="mt-1 text-xs text-red-500 flex items-center gap-1"><span class="font-medium">{{ $message }}</span></p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan Aset</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
