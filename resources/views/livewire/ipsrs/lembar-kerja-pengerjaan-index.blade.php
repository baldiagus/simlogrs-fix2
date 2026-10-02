<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Lembar Kerja Pengerjaan IPSRS</flux:heading>
                <flux:subheading>Pencatatan diagnosis teknis, tindakan perbaikan fasilitas, pemakaian suku cadang FEFO, dan uji fungsi bersama unit.</flux:subheading>
            </div>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode SPK, teknisi, alat..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-48">
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="AKTIF">Tugas Aktif Berjalan</option>
                    <option value="SELESAI">Riwayat Selesai</option>
                    <option value="SEMUA">Semua SPK</option>
                </select>
            </div>
        </div>

        {{-- Tabel Daftar SPK --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">No. SPK</flux:table.column>
                <flux:table.column>Fasilitas / Sarana</flux:table.column>
                <flux:table.column>Unit Ruangan</flux:table.column>
                <flux:table.column>Teknisi Pelaksana</flux:table.column>
                <flux:table.column class="text-center w-28">Log Servis</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-32">Status</flux:table.column>
                <flux:table.column class="text-right w-48">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarSpk as $spk)
                    <flux:table.row :key="$spk->id">
                        {{-- Kode SPK --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            <div>{{ $spk->kode }}</div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-sans mt-0.5">
                                {{ $spk->tanggal_penugasan?->format('d/m/Y') }}
                            </div>
                        </flux:table.cell>

                        {{-- Fasilitas / Sarana --}}
                        <flux:table.cell>
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $spk->permintaanMaintenance?->aset?->barang?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Kode: {{ $spk->permintaanMaintenance?->aset?->kode_aset ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Unit Ruangan --}}
                        <flux:table.cell>
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $spk->permintaanMaintenance?->unit?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Teknisi Pelaksana --}}
                        <flux:table.cell>
                            <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $spk->teknisi?->nama ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Log Servis --}}
                        <flux:table.cell class="text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                                {{ $spk->logMaintenance->count() }} log
                            </span>
                        </flux:table.cell>

                        {{-- Status SPK --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$spk->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button
                                    wire:click="showDetail({{ $spk->id }})"
                                    size="xs"
                                    variant="ghost"
                                    icon="eye"
                                    title="Lihat Rincian Pengerjaan"
                                />

                                @if (in_array($spk->status, ['DITUGASKAN', 'DITERIMA']))
                                    @can('create_pemeliharaan::lembar::kerja')
                                        <flux:button
                                            wire:click="openLembarKerja({{ $spk->id }})"
                                            size="xs"
                                            variant="primary"
                                            icon="wrench-screwdriver"
                                        >
                                            Input Servis
                                        </flux:button>
                                    @endcan
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Tidak ada SPK perbaikan yang sesuai dengan filter." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$daftarSpk" />
    </flux:card>

    {{-- Modal Form Input Lembar Kerja & Pemakaian Sparepart --}}
    <flux:modal name="modal-lembar-kerja" wire:model="showModal" class="w-full max-w-4xl">
        @if ($formSpk)
            <form wire:submit="saveLembarKerja" class="space-y-4">
                <div>
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
                                    $selectedSp = $daftarSparepart->firstWhere('id', (int) ($row['barang_id'] ?? 0));
                                    $maxStok = $selectedSp ? (int) ($selectedSp->stok_tersedia ?? 0) : null;
                                @endphp
                                <div class="p-2.5 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-1.5" wire:key="sparepart-row-{{ $index }}">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1">
                                            <select
                                                wire:model.live="sparepartItems.{{ $index }}.barang_id"
                                                required
                                                class="w-full text-xs rounded-lg border {{ $errors->has("sparepartItems.{$index}.barang_id") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            >
                                                <option value="">-- Pilih Komoditas Suku Cadang --</option>
                                                @foreach ($daftarSparepart as $sp)
                                                    @php
                                                        $stokAda = (int) ($sp->stok_tersedia ?? 0);
                                                    @endphp
                                                    <option value="{{ $sp->id }}" @disabled($stokAda <= 0)>
                                                        [{{ $sp->kode }}] {{ $sp->nama }} &bull; Sisa: {{ number_format($stokAda, 0, ',', '.') }} {{ $sp->satuan?->simbol ?? 'Unit' }} {{ $stokAda <= 0 ? '(HABIS)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
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
                                                Maksimal stok: <strong class="{{ $maxStok > 0 ? 'text-zinc-800 dark:text-zinc-200' : 'text-rose-600' }}">{{ number_format($maxStok, 0, ',', '.') }} {{ $selectedSp->satuan?->simbol ?? 'Unit' }}</strong>
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

                <div class="flex justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveLembarKerja">Simpan Lembar Kerja & Potong Sparepart</span>
                        <span wire:loading wire:target="saveLembarKerja">Memproses Stok...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    {{-- Modal Detail SPK & Riwayat Lembar Kerja --}}
    <flux:modal name="modal-detail-spk-log" wire:model="showDetailModal" class="w-full max-w-4xl">
        @if ($selectedSpk)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail SPK: {{ $selectedSpk->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Fasilitas: {{ $selectedSpk->permintaanMaintenance?->aset?->barang?->nama }} &bull;
                            Unit: {{ $selectedSpk->permintaanMaintenance?->unit?->nama }}
                        </flux:subheading>
                    </div>
                    <x-status-badge :status="$selectedSpk->status" />
                </div>
                {{-- Keluhan & Foto Bukti Fisik Kerusakan dari Ruangan --}}
                <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/40 text-xs space-y-2">
                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 flex items-center justify-between">
                        <span>Laporan Keluhan Kerusakan:</span>
                        @if ($selectedSpk->permintaanMaintenance?->prioritas)
                            <flux:badge size="sm" :color="match($selectedSpk->permintaanMaintenance->prioritas) {
                                'CITO' => 'rose',
                                'TINGGI' => 'amber',
                                'SEDANG' => 'blue',
                                default => 'zinc',
                            }">
                                {{ $selectedSpk->permintaanMaintenance->prioritas }}
                            </flux:badge>
                        @endif
                    </div>
                    <div class="text-zinc-700 dark:text-zinc-300 whitespace-pre-line">
                        {{ $selectedSpk->permintaanMaintenance?->deskripsi_masalah ?? '-' }}
                    </div>

                    @if ($selectedSpk->permintaanMaintenance?->foto_kerusakan)
                        <div class="pt-2 border-t border-zinc-200/60 dark:border-zinc-700">
                            <div class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1.5">Foto Bukti Kerusakan Fisik dari Pelapor:</div>
                            <div class="flex flex-col sm:flex-row items-start gap-3">
                                <a href="{{ asset('storage/' . $selectedSpk->permintaanMaintenance->foto_kerusakan) }}" target="_blank" class="group relative block rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 max-w-xs shadow-sm hover:shadow transition">
                                    <img
                                        src="{{ asset('storage/' . $selectedSpk->permintaanMaintenance->foto_kerusakan) }}"
                                        alt="Foto Kerusakan {{ $selectedSpk->permintaanMaintenance->kode }}"
                                        class="max-h-40 w-auto object-cover rounded group-hover:scale-105 transition duration-300"
                                    />
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white font-medium text-[11px] gap-1 transition">
                                        <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                        <span>Buka Foto</span>
                                    </div>
                                </a>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 space-y-1">
                                    <div>Foto kondisi fisik saat dilaporkan oleh unit.</div>
                                    <a href="{{ asset('storage/' . $selectedSpk->permintaanMaintenance->foto_kerusakan) }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                        <flux:icon name="magnifying-glass-plus" class="size-3" />
                                        Lihat resolusi penuh
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>


                {{-- Riwayat Lembar Kerja Pengerjaan --}}
                <div class="space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Catatan Pengerjaan & Penggantian Komponen</div>
                    @forelse ($selectedSpk->logMaintenance as $log)
                        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900 text-xs space-y-2">
                            <div class="flex items-center justify-between pb-1.5 border-b border-zinc-200/60 dark:border-zinc-800">
                                <div>
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">Teknisi: {{ $log->teknisi?->nama }}</span>
                                    <span class="text-zinc-500 dark:text-zinc-400 ml-2">({{ $log->tanggal_pengerjaan?->format('d/m/Y H:i') }})</span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $log->hasil === 'BERHASIL' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                    {{ $log->hasil }}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div><span class="text-zinc-500 dark:text-zinc-400">Diagnosa:</span> {{ $log->diagnosa }}</div>
                                <div><span class="text-zinc-500 dark:text-zinc-400">Tindakan:</span> {{ $log->tindakan }}</div>
                            </div>

                            @if ($log->sukuCadang->isNotEmpty())
                                <div class="pt-2 border-t border-zinc-200/60 dark:border-zinc-800">
                                    <div class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Suku Cadang Digunakan:</div>
                                    <div class="space-y-1 font-mono text-[11px]">
                                        @foreach ($log->sukuCadang as $sc)
                                            <div class="flex items-center justify-between text-zinc-700 dark:text-zinc-300">
                                                <span>&bull; {{ $sc->barang?->nama }} (Batch: {{ $sc->stokBatch?->nomor_batch ?? '-' }})</span>
                                                <span class="font-semibold">{{ $sc->jumlah_digunakan }} {{ $sc->barang?->satuan?->simbol ?? 'Unit' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-zinc-500 dark:text-zinc-400 italic border rounded-lg">
                            Belum ada catatan lembar kerja untuk SPK ini.
                        </div>
                    @endforelse
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
