    {{-- Modal Ubah Status Aset Ruangan --}}
    <flux:modal name="modal-ubah-status-aset" wire:model="showUbahStatusAsetModal" class="md:w-[30rem]">
        @if ($selectedAset)
            <form wire:submit="simpanStatusAset" class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Update Status Operasional Aset</flux:heading>
                    <flux:subheading>Sesuaikan status alat inventaris ruangan (sedang aktif digunakan atau siap pakai/dipinjamkan).</flux:subheading>
                </div>

                {{-- Detail Aset --}}
                <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/60 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Kode Aset:</span>
                        <span class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->kode_aset }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Nama Sarana:</span>
                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->barang?->nama }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Nomor Seri:</span>
                        <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $selectedAset->nomor_seri ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Unit Penempatan:</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $selectedAset->unit?->nama ?? '-' }}</span>
                    </div>
                </div>

                {{-- Status Baru --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Status Operasional Aset <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="statusAsetBaru"
                        class="w-full h-9 text-xs rounded-lg border {{ $errors->has('statusAsetBaru') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                        <option value="DIGUNAKAN">DIGUNAKAN (Sedang aktif beroperasi dalam pelayanan ruangan)</option>
                        <option value="TERSEDIA">TERSEDIA (Alat idle / cadangan siap pakai & siap dipinjamkan)</option>
                    </select>
                    @error('statusAsetBaru') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Kondisi Fisik --}}
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                        Kondisi Fisik Alat <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="kondisiAsetBaru"
                        class="w-full h-9 text-xs rounded-lg border {{ $errors->has('kondisiAsetBaru') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                        <option value="BAIK">BAIK (Berfungsi normal tanpa kendala)</option>
                        <option value="RUSAK_RINGAN">RUSAK RINGAN (Fungsi terbatas / ada bagian aus)</option>
                        <option value="RUSAK_BERAT">RUSAK BERAT (Mati total / tidak dapat digunakan)</option>
                    </select>
                    @error('kondisiAsetBaru') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                </div>

                {{-- Tombol Aksi Modal --}}
                <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="simpanStatusAset">Simpan Perubahan</span>
                        <span wire:loading wire:target="simpanStatusAset">Menyimpan...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
