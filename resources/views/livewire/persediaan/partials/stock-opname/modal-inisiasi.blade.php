    {{-- Modal Inisiasi Siklus Opname Baru --}}
    <flux:modal name="modal-inisiasi-opname" wire:model="showInisiasiModal" class="md:w-[32rem]">
        <form wire:submit="inisiasiOpname" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Buka Siklus Stock Opname</flux:heading>
                <flux:subheading>Inisialisasi lembar hitung fisik persediaan untuk fasilitas gudang tertentu.</flux:subheading>
            </div>

            {{-- Tahap 1: Pemilihan Gudang & Jadwal --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Fasilitas Gudang & Jadwal Pelaksanaan Sesi
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Fasilitas Gudang yang Diopname <span class="text-red-500">*</span>
                    </label>
                    <select
                        wire:model="gudang_id"
                        required
                        class="w-full text-sm rounded-lg border {{ $errors->has('gudang_id') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none"
                    >
                        <option value="">-- Pilih Fasilitas Gudang --</option>
                        @foreach ($daftarGudang as $gdg)
                            <option value="{{ $gdg->id }}">{{ $gdg->nama }} ({{ $gdg->kode }})</option>
                        @endforeach
                    </select>
                    @error('gudang_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <flux:field>
                    <flux:label>Tanggal Pelaksanaan Opname <span class="text-red-500">*</span></flux:label>
                    <flux:input type="date" wire:model="tanggal_opname" required />
                    <flux:error name="tanggal_opname" />
                </flux:field>
            </div>

            {{-- Tahap 2: Catatan Instruksi Tim Pemeriksa --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 2: Instruksi Khusus & Catatan Sesi Opname
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Catatan Pelaksanaan (Opsional)
                    </label>
                    <textarea
                        wire:model="catatan"
                        rows="2"
                        placeholder="Contoh: Opname triwulan persediaan alat rumah tangga dan ATK sentral..."
                        class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    ></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showInisiasiModal', false)">
                    Batal
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="inisiasiOpname">Buka Siklus Opname</span>
                    <span wire:loading wire:target="inisiasiOpname">Menyiapkan Batch...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
