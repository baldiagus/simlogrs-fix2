    {{-- Modal Form Tambah PR --}}
    <flux:modal name="modal-pr-form" wire:model="showModal" class="md:w-[48rem]">
        <form wire:submit="save" class="space-y-4">
            <div style="padding-right: 2.5rem;">
                <flux:heading size="lg">Buat Permintaan Pengadaan Baru</flux:heading>
                <flux:subheading>Lengkapi informasi permintaan dan daftar barang yang dibutuhkan.</flux:subheading>
            </div>

            {{-- Tahap 1: Dokumen, Entitas Pemohon & Urgensi PR --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-4">
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Tahap 1: Dokumen, Entitas Pemohon & Tingkat Urgensi
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="kode" label="Kode PR (Otomatis)" disabled readonly class="font-mono bg-zinc-100 dark:bg-zinc-800/80 cursor-not-allowed font-semibold" />

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Urgensi <span class="text-red-500">*</span></label>
                        <select wire:model="urgensi" required class="w-full text-sm rounded-lg border {{ $errors->has('urgensi') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none">
                            <option value="REGULER">REGULER</option>
                            <option value="MENDESAK">MENDESAK</option>
                            <option value="CITO">CITO</option>
                        </select>
                        @error('urgensi') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Pilihan Pemohon: Unit vs Gudang (Mutually Exclusive) --}}
                <div class="p-3.5 rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/60 space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-zinc-200/60 dark:border-zinc-700/60">
                        <div>
                            <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Entitas Pemohon Pengadaan <span class="text-red-500">*</span></div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Pilih salah satu: Unit Ruangan ATAU Gudang Logistik. Memilih salah satu akan menonaktifkan pilihan lainnya.</div>
                        </div>
                        @if ($unit_id || $gudang_id)
                            <button type="button" wire:click="resetPemohon" class="text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline flex items-center gap-1">
                                <flux:icon name="arrow-path" class="size-3" />
                                Reset Pilihan
                            </button>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-ui.searchable-select
                                model="unit_id"
                                label="Unit / Ruangan Pemohon"
                                placeholder="-- Pilih Unit Ruangan --"
                                :options="$daftarUnit"
                                :disabled="!empty($gudang_id)"
                                live="true"
                            />
                            @if ($gudang_id)
                                <p class="text-[10px] text-zinc-400 mt-1 italic">Nonaktif karena Gudang Pemohon telah dipilih.</p>
                            @endif
                        </div>

                        <div>
                            <x-ui.searchable-select
                                model="gudang_id"
                                label="Gudang Pemohon"
                                placeholder="-- Pilih Gudang Logistik --"
                                :options="$daftarGudang"
                                :disabled="!empty($unit_id)"
                                live="true"
                            />
                            @if ($unit_id)
                                <p class="text-[10px] text-zinc-400 mt-1 italic">Nonaktif karena Unit Pemohon telah dipilih.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <flux:textarea wire:model="catatan" label="Catatan / Justifikasi" placeholder="Alasan atau keterangan tambahan kebutuhan pengadaan..." rows="2" />
            </div>
            {{-- Daftar Item Barang --}}
            {{-- Tahap 2: Daftar Barang Diminta --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                        Tahap 2: Rincian Daftar Komoditas Barang yang Diminta
                    </div>
                    <flux:button type="button" wire:click="addItem" size="xs" variant="ghost" icon="plus">Tambah Baris</flux:button>
                </div>
                @foreach ($items as $index => $row)
                    <div class="flex items-end gap-3" wire:key="item-{{ $index }}">
                        <div class="flex-1">
                            @if ($loop->first)
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Barang</label>
                            @endif
                            <x-ui.searchable-select
                                model="items.{{ $index }}.barang_id"
                                placeholder="Pilih Barang..."
                                :options="$daftarBarang"
                                required
                            />
                            @error("items.{$index}.barang_id") <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="w-28">
                            @if ($loop->first)
                                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Jumlah</label>
                            @endif
                            <input type="number" wire:model="items.{{ $index }}.jumlah" min="1" required class="w-full text-sm rounded-lg border {{ $errors->has("items.{$index}.jumlah") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 text-right tabular-nums" />
                            @error("items.{$index}.jumlah") <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                        </div>
                        <div class="w-10 shrink-0">
                            @if (count($items) > 1)
                                <flux:button type="button" wire:click="removeItem({{ $index }})" size="xs" variant="ghost" icon="trash" title="Hapus Baris" class="text-red-500 hover:text-red-700" />
                            @endif
                        </div>
                    </div>
                @endforeach

                @error('items') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Ajukan Permintaan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
