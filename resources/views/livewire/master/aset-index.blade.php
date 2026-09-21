<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header & Tombol Tambah --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Register Master Aset</flux:heading>
                <flux:subheading>Pencatatan inventaris aktiva fisik rumah sakit, nomor seri, penempatan lokasi, dan kode QR aset.</flux:subheading>
            </div>

            @can('create_master::aset')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Register Aset Baru
                </flux:button>
            @endcan
        </div>

        {{-- Filter Lokasi Penempatan (Pills) --}}
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
            <button
                wire:click="$set('filterLokasi', '')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterLokasi === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Semua Penempatan
            </button>
            <button
                wire:click="$set('filterLokasi', 'RUANGAN')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterLokasi === 'RUANGAN' ? 'bg-white dark:bg-zinc-900 text-cyan-600 dark:text-cyan-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Di Ruangan / Unit
            </button>
            <button
                wire:click="$set('filterLokasi', 'GUDANG')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterLokasi === 'GUDANG' ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Di Gudang Logistik
            </button>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode aset, nomor seri, nama barang..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-44">
                <select wire:model.live="filterStatus" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status Aset</option>
                    <option value="TERSEDIA">TERSEDIA</option>
                    <option value="DIGUNAKAN">DIGUNAKAN</option>
                    <option value="DIPINJAM">DIPINJAM</option>
                    <option value="DALAM_MAINTENANCE">DALAM MAINTENANCE</option>
                    <option value="DIHAPUSKAN">DIHAPUSKAN</option>
                </select>
            </div>

            <div class="w-full sm:w-40">
                <select wire:model.live="filterKondisi" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Kondisi Fisik</option>
                    <option value="BAIK">Kondisi BAIK</option>
                    <option value="RUSAK_RINGAN">RUSAK RINGAN</option>
                    <option value="RUSAK_BERAT">RUSAK BERAT</option>
                </select>
            </div>
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode_aset'" :direction="$sortDirection" wire:click="sort('kode_aset')" class="w-32">Kode Register</flux:table.column>
                <flux:table.column>Nama Komoditas & Seri</flux:table.column>
                <flux:table.column>Penempatan Lokasi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'kondisi'" :direction="$sortDirection" wire:click="sort('kondisi')" class="text-center">Kondisi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nilai_perolehan'" :direction="$sortDirection" wire:click="sort('nilai_perolehan')" class="text-right">Nilai Perolehan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-28">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        {{-- Kode Aset --}}
                        <flux:table.cell class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode_aset }}
                        </flux:table.cell>

                        {{-- Nama Barang & Nomor Seri --}}
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->barang?->nama ?? '-' }}</div>
                            @if ($item->nomor_seri)
                                <div class="font-mono text-[11px] text-zinc-400">SN: {{ $item->nomor_seri }}</div>
                            @endif
                        </flux:table.cell>

                        {{-- Lokasi Penempatan (XOR Ruangan atau Gudang) --}}
                        <flux:table.cell>
                            @if ($item->unit_id)
                                <div class="flex items-center gap-1.5">
                                    <span class="size-1.5 rounded-full bg-cyan-500"></span>
                                    <span class="text-xs font-medium text-cyan-800 dark:text-cyan-300">Ruangan: {{ $item->unit?->nama }}</span>
                                </div>
                            @elseif ($item->gudang_id)
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                                        <span class="text-xs font-medium text-amber-800 dark:text-amber-300">Gudang: {{ $item->gudang?->nama }}</span>
                                    </div>
                                    @if ($item->lokasiGudang)
                                        <div class="text-[11px] font-mono text-zinc-400 pl-3">Rak: {{ $item->lokasiGudang->nama }} ({{ $item->lokasiGudang->kode }})</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-red-500 italic">Tanpa Lokasi</span>
                            @endif
                        </flux:table.cell>

                        {{-- Kondisi Fisik --}}
                        <flux:table.cell class="text-center">
                            @php
                            $kondisiColor = match($item->kondisi) {
                                'BAIK' => 'emerald',
                                'RUSAK_RINGAN' => 'amber',
                                'RUSAK_BERAT' => 'rose',
                                default => 'zinc',
                            };
                            @endphp
                            <flux:badge size="sm" :color="$kondisiColor">
                                {{ str_replace('_', ' ', $item->kondisi) }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Nilai Perolehan --}}
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                            <x-rupiah :value="$item->nilai_perolehan" class="font-semibold text-zinc-900 dark:text-zinc-100" />
                            <div class="text-[10px] text-zinc-400">{{ $item->tanggal_perolehan->format('d/m/Y') }}</div>
                        </flux:table.cell>

                        {{-- Status Aset --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <flux:button wire:click="showQr({{ $item->id }})" size="xs" variant="ghost" icon="qr-code" title="Cetak Stiker QR Code" />

                                @can('update_master::aset')
                                    <flux:button wire:click="edit({{ $item->id }})" size="xs" variant="ghost" icon="pencil-square" title="Ubah Data" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada unit fisik aset yang terdaftar atau sesuai filter." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Footer Pagination Terpadu: Kiri (Showing), Tengah (Baris per halaman), Kanan (Tombol) --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Form Tambah / Ubah Aset --}}
    <flux:modal name="modal-aset-form" wire:model="showModal" class="md:w-[38rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah Register Aset' : 'Registrasi Unit Aset Baru' }}</flux:heading>
                <flux:subheading>Pencatatan aktiva inventaris, nomor seri, dan lokasi penempatan.</flux:subheading>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Komoditas Aset</label>
                <select wire:model="barang_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                    <option value="">Pilih Barang (Tipe Aset)...</option>
                    @foreach ($daftarBarangAset as $b)
                        <option value="{{ $b->id }}">{{ $b->nama }} ({{ $b->kode }})</option>
                    @endforeach
                </select>
                @error('barang_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Penempatan Lokasi Fisik (Ruangan ATAU Gudang)</div>

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
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Ruangan / Instalasi Dinas</label>
                        <select wire:model="unit_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            <option value="">Pilih Ruangan...</option>
                            @foreach ($daftarUnit as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->nama }} ({{ $unit->kode }})</option>
                            @endforeach
                        </select>
                        @error('unit_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Gudang Logistik</label>
                            <select wire:model.live="gudang_id" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                                <option value="">Pilih Gudang...</option>
                                @foreach ($daftarGudang as $gudang)
                                    <option value="{{ $gudang->id }}">{{ $gudang->nama }} ({{ $gudang->kode }})</option>
                                @endforeach
                            </select>
                            @error('gudang_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Rak Simpan (Opsional)</label>
                            <select wire:model="lokasi_gudang_id" class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                                <option value="">Tanpa Rak Khusus</option>
                                @foreach ($daftarRak as $rak)
                                    <option value="{{ $rak->id }}">{{ $rak->nama }} ({{ $rak->kode }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif
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
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Kondisi Fisik</label>
                    <select wire:model="kondisi" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="BAIK">BAIK (Siap Operasional)</option>
                        <option value="RUSAK_RINGAN">RUSAK RINGAN</option>
                        <option value="RUSAK_BERAT">RUSAK BERAT</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5">Status Aset</label>
                    <select wire:model="status" required class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                        <option value="TERSEDIA">TERSEDIA</option>
                        <option value="DIGUNAKAN">DIGUNAKAN</option>
                        <option value="DIPINJAM">DIPINJAM</option>
                        <option value="DALAM_MAINTENANCE">DALAM MAINTENANCE</option>
                        <option value="DIHAPUSKAN">DIHAPUSKAN</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Simpan Aset</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Stiker QR Code --}}
    <flux:modal name="modal-qr-code" wire:model="showQrModal" class="md:w-96">
        @if ($selectedAset)
            <div class="space-y-5 text-center p-2">
                <div>
                    <flux:heading size="lg">Stiker QR Code Aset</flux:heading>
                    <flux:subheading>Tempelkan stiker ini pada unit fisik barang inventaris.</flux:subheading>
                </div>

                <div id="print-area" class="p-6 bg-white dark:bg-zinc-900 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-2xl inline-block shadow-xs">
                    <div class="flex justify-center mb-3">
                        {!! $qrCodeSvg !!}
                    </div>
                    <div class="font-mono font-extrabold text-sm text-zinc-900 dark:text-zinc-100 tracking-wider">
                        {{ $selectedAset->kode_aset }}
                    </div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mt-1 max-w-[200px] truncate">
                        {{ $selectedAset->barang?->nama }}
                    </div>
                    @if ($selectedAset->nomor_seri)
                        <div class="font-mono text-[10px] text-zinc-400">SN: {{ $selectedAset->nomor_seri }}</div>
                    @endif
                    <div class="text-[10px] uppercase font-bold text-zinc-500 mt-2 border-t border-zinc-200 dark:border-zinc-800 pt-1.5">
                        SIMLOG RS • INVENTARIS
                    </div>
                </div>

                <div class="flex justify-center gap-2 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showQrModal', false)">Tutup</flux:button>
                    <flux:button variant="primary" icon="printer" onclick="window.print()">Cetak Stiker</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
