    {{-- Modal Form Input Lembar Kerja & Pemakaian Sparepart --}}
    <flux:modal name="modal-lembar-kerja" wire:model="showModal" class="w-full max-w-4xl">
        @if ($formSpk)
            <form wire:submit="saveLembarKerja" class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Lembar Kerja Servis: {{ $formSpk->kode }}</flux:heading>
                    <flux:subheading>
                        Sarana: <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $formSpk->permintaanMaintenance?->aset?->barang?->nama }}</span>
                        ({{ $formSpk->permintaanMaintenance?->aset?->kode_aset }}) &bull;
                        Unit: <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $formSpk->permintaanMaintenance?->unit?->nama }}</span>
                    </flux:subheading>
                </div>

                {{-- Deskripsi Keluhan Awal & Foto Kerusakan --}}
                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs space-y-2">
                    <div>
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Keluhan Pelapor:</span>
                        <span class="text-zinc-600 dark:text-zinc-400 ml-1">{{ $formSpk->permintaanMaintenance?->deskripsi_masalah }}</span>
                    </div>
                    @if ($formSpk->permintaanMaintenance?->foto_kerusakan)
                        <div class="flex items-center gap-3 pt-1 border-t border-zinc-200/60 dark:border-zinc-700/60">
                            <a href="{{ asset('storage/' . $formSpk->permintaanMaintenance->foto_kerusakan) }}" target="_blank" class="block rounded border border-zinc-200 dark:border-zinc-700 overflow-hidden hover:opacity-90">
                                <img src="{{ asset('storage/' . $formSpk->permintaanMaintenance->foto_kerusakan) }}" alt="Foto Kerusakan" class="h-14 w-auto object-cover" />
                            </a>
                            <a href="{{ asset('storage/' . $formSpk->permintaanMaintenance->foto_kerusakan) }}" target="_blank" class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-medium">
                                <flux:icon name="magnifying-glass-plus" class="size-3.5" />
                                 Lihat Foto Bukti Kerusakan Awal
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Baris 1: Waktu Pengerjaan --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                            Tanggal Pengerjaan <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="datetime-local"
                            wire:model="tanggal_pengerjaan"
                            required
                            class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        @error('tanggal_pengerjaan') <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                            Jam Mulai
                        </label>
                        <input
                            type="time"
                            wire:model="jam_mulai"
                            class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                            Jam Selesai
                        </label>
                        <input
                            type="time"
                            wire:model="jam_selesai"
                            class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                </div>

                {{-- Baris 2: Diagnosa & Tindakan --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                            Diagnosa Teknis / Sumber Kerusakan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            wire:model="diagnosa"
                            rows="2"
                            required
                            placeholder="Contoh: Modul power supply korsleting, kapasitor filter meledak..."
                            class="w-full text-xs rounded-lg border {{ $errors->has('diagnosa') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        ></textarea>
                        @error('diagnosa') <p class="text-[10px] text-red-500 mt-0.5 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                            Tindakan Perbaikan yang Dilakukan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            wire:model="tindakan"
                            rows="2"
                            required
                            placeholder="Contoh: Pembersihan debu sirkulasi, penggantian kapasitor baru, pengelasan kabel ground..."
                            class="w-full text-xs rounded-lg border {{ $errors->has('tindakan') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        ></textarea>
                        @error('tindakan') <p class="text-[10px] text-red-500 mt-0.5 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Baris 3: Pemakaian Suku Cadang (Sparepart FEFO) --}}
                <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Penggantian Suku Cadang (Sparepart)</div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">Otomatis memotong stok fisik gudang berbasis FEFO dan mencatat mutasi pengeluaran.</div>
                        </div>
                        <flux:button type="button" wire:click="addSparepartRow" size="xs" variant="ghost" icon="plus">
                            Tambah Suku Cadang
                        </flux:button>
                    </div>

                    @if (empty($sparepartItems))
                        <div class="p-3 text-center text-xs text-zinc-500 dark:text-zinc-400 italic bg-white dark:bg-zinc-800/40 rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700">
                            Tidak ada suku cadang yang diganti pada servis ini (reparasi tanpa pergantian komponen).
                        </div>
                    @else
                        <div class="space-y-2.5">
                            @foreach ($sparepartItems as $index => $row)
                                @php
                                    $selectedSp = collect($daftarSparepart)->firstWhere('id', (int) ($row['barang_id'] ?? 0));
                                    $maxStok = $selectedSp ? (int) ($selectedSp['stok_tersedia'] ?? 0) : null;
                                @endphp
                                <div class="p-2.5 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-1.5" wire:key="sparepart-row-{{ $index }}">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 min-w-0">
                                            <x-ui.searchable-select
                                                model="sparepartItems.{{ $index }}.barang_id"
                                                placeholder="-- Cari Nama, Kode, atau Kategori Suku Cadang... --"
                                                :options="$daftarSparepart"
                                                :live="true"
                                                :nullable="false"
                                            />
                                        </div>
                                        <div class="w-28">
                                            <input
                                                type="number"
                                                wire:model.live="sparepartItems.{{ $index }}.jumlah"
                                                min="1"
                                                @if ($maxStok !== null) max="{{ $maxStok }}" @endif
                                                required
                                                placeholder="Jumlah"
                                                class="w-full text-xs text-right font-mono tabular-nums rounded-lg border {{ $errors->has("sparepartItems.{$index}.jumlah") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                        </div>
                                        <flux:button
                                            type="button"
                                            wire:click="removeSparepartRow({{ $index }})"
                                            size="xs"
                                            variant="ghost"
                                            icon="trash"
                                            class="text-rose-600 hover:text-rose-700 shrink-0"
                                            title="Hapus Suku Cadang"
                                        />
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] px-0.5">
                                        @error("sparepartItems.{$index}.barang_id")
                                            <span class="text-rose-600 dark:text-rose-400 font-medium">{{ $message }}</span>
                                        @else
                                            <span></span>
                                        @enderror
                                        @if ($maxStok !== null)
                                            <span class="font-mono text-zinc-500 dark:text-zinc-400">
                                                Maksimal stok: <strong class="{{ $maxStok > 0 ? 'text-zinc-800 dark:text-zinc-200' : 'text-rose-600' }}">{{ number_format($maxStok, 0, ',', '.') }} {{ $selectedSp['satuan'] ?? 'Unit' }}</strong>
                                            </span>
                                        @endif
                                    </div>
                                    @error("sparepartItems.{$index}.jumlah")
                                        <div class="text-[11px] text-rose-600 dark:text-rose-400 font-medium px-0.5 text-right">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Baris 4: Hasil Servis & Uji Fungsi Kelaikan --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">
                            Hasil Akhir Pengerjaan Fisik <span class="text-red-500">*</span>
                        </label>
                        <select
                            wire:model.live="hasil"
                            required
                            class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="BERHASIL">BERHASIL (Perbaikan tuntas)</option>
                            <option value="PENDING">PENDING (Pengerjaan dilanjutkan besok / butuh alat khusus)</option>
                            <option value="GAGAL">GAGAL (Menunggu pemesanan suku cadang baru)</option>
                        </select>
                    </div>

                    <div class="flex items-center">
                        @if ($hasil === 'BERHASIL')
                            <label class="flex items-start gap-2 cursor-pointer p-2 rounded-lg hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20">
                                <input
                                    type="checkbox"
                                    wire:model="uji_fungsi_kelaikan"
                                    class="mt-0.5 text-emerald-600 focus:ring-emerald-500 rounded"
                                />
                                <div class="text-xs">
                                    <div class="font-bold text-emerald-800 dark:text-emerald-300">Uji Kelaikan Fungsi Bersama Ruangan Lolos</div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Centang untuk menutup SPK, menyelesaikan tiket, dan mengembalikan aset ke status BAIK.</div>
                                </div>
                            </label>
                        @else
                            <div class="text-xs text-amber-700 dark:text-amber-300 italic p-2">
                                Status tiket akan dialihkan ke antrean pengerjaan lanjutan atau menunggu pengadaan sparepart.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveLembarKerja">Simpan Lembar Kerja & Potong Sparepart</span>
                        <span wire:loading wire:target="saveLembarKerja">Memproses Stok...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
