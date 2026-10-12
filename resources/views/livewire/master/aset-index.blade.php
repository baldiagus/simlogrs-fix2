<div class="space-y-6">
    {{-- Header Halaman & Tombol Tambah --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Register Master Aset</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Pencatatan inventaris aktiva fisik rumah sakit, nomor seri, penempatan lokasi, dan kode QR aset.</flux:subheading>
            </div>

            @can('create_master::aset')
                <flux:button wire:click="create" variant="primary" icon="plus" wire:loading.attr="disabled">
                    Register Aset Baru
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Filter Lokasi Penempatan (Pills) --}}
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
            <button
                wire:click="$set('filterLokasi', '')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterLokasi === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Semua Penempatan
            </button>
            <button
                wire:click="$set('filterLokasi', 'RUANGAN')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterLokasi === 'RUANGAN' ? 'bg-white dark:bg-zinc-900 text-cyan-600 dark:text-cyan-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Di Ruangan / Unit
            </button>
            <button
                wire:click="$set('filterLokasi', 'GUDANG')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterLokasi === 'GUDANG' ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
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
                <select wire:model.live="filterStatus" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Status Aset</option>
                    @foreach (\App\Enums\StatusAset::dapatDiaturManual() as $statusAset)
                        <option value="{{ $statusAset }}">{{ str_replace('_', ' ', $statusAset) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-40">
                <select wire:model.live="filterKondisi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Kondisi Fisik</option>
                    <option value="BAIK">Kondisi BAIK</option>
                    <option value="RUSAK_RINGAN">RUSAK RINGAN</option>
                    <option value="RUSAK_BERAT">RUSAK BERAT</option>
                </select>
            </div>
            @if ($search || $filterLokasi || $filterStatus || $filterKondisi || $filterUnitId || $filterGudangId)
                <flux:button
                    wire:click="resetFilters"
                    size="sm"
                    variant="subtle"
                    icon="x-mark"
                >
                    Reset Filter
                </flux:button>
            @endif
        </div>

        {{-- Tabel Bleed dalam Card --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'kode_aset'" :direction="$sortDirection" wire:click="sort('kode_aset')" class="w-32">Kode Register</flux:table.column>
                <flux:table.column class="w-64">Nama Komoditas & Seri</flux:table.column>
                <flux:table.column class="w-48">Penempatan Lokasi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'kondisi'" :direction="$sortDirection" wire:click="sort('kondisi')" class="text-center w-28">Kondisi</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'nilai_perolehan'" :direction="$sortDirection" wire:click="sort('nilai_perolehan')" class="text-right w-36">Nilai Perolehan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-24">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    <flux:table.row :key="$item->id">
                        {{-- Kode Aset --}}
                        <flux:table.cell class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->kode_aset }}
                        </flux:table.cell>

                        {{-- Nama Barang & Nomor Seri --}}
                        <flux:table.cell style="white-space: normal;">
                            <button
                                type="button"
                                wire:click="showDetail({{ $item->id }})"
                                class="text-left font-medium text-xs text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-2"
                                title="Lihat Detail Aset"
                            >
                                {{ $item->barang?->nama ?? '-' }}
                            </button>
                            @if ($item->nomor_seri)
                                <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">SN: {{ $item->nomor_seri }}</div>
                            @endif
                        </flux:table.cell>

                        {{-- Lokasi Penempatan (XOR Ruangan atau Gudang) --}}
                        <flux:table.cell style="white-space: normal;">
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
                                        <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 pl-3">Rak: {{ $item->lokasiGudang->nama }} ({{ $item->lokasiGudang->kode }})</div>
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
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">{{ $item->tanggal_perolehan->format('d/m/Y') }}</div>
                        </flux:table.cell>

                        {{-- Status Aset --}}
                        <flux:table.cell class="text-center">
                            <x-status-badge :status="$item->status" />
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right w-16">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                <flux:menu class="min-w-44">
                                    <flux:menu.item wire:click="showDetail({{ $item->id }})" icon="eye">
                                        Detail Aset
                                    </flux:menu.item>
                                    <flux:menu.item wire:click="showQr({{ $item->id }})" icon="qr-code">
                                        Cetak Stiker QR
                                    </flux:menu.item>
                                    @php
                                        $user = auth()->user();
                                        $canEditThisAset = $this->canUserEditAset($user, $item);
                                    @endphp
                                    @if ($canEditThisAset)
                                        <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil-square">
                                            Ubah Data
                                        </flux:menu.item>
                                    @endif
                                    @if ($this->canUserDeleteAset($user, $item))
                                        <flux:menu.separator />
                                        <flux:menu.item wire:click="confirmDelete({{ $item->id }})" icon="trash" variant="danger">
                                            Hapus Data
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
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
                        <div class="font-mono text-[10px] text-zinc-500 dark:text-zinc-400">SN: {{ $selectedAset->nomor_seri }}</div>
                    @endif
                    <div class="text-[10px] uppercase font-bold text-zinc-500 mt-2 border-t border-zinc-200 dark:border-zinc-800 pt-1.5">
                        SIMLOG RS • INVENTARIS
                    </div>
                </div>

                <div class="flex justify-center pt-2">
                    <flux:button variant="primary" icon="printer" onclick="window.print()">Cetak Stiker</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Detail Aset Inventaris --}}
    <flux:modal wire:model="showDetailModal" class="md:w-[44rem]">
        @if ($selectedAset)
            <div class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Detail Aset Inventaris: {{ $selectedAset->barang?->nama }}</flux:heading>
                    <flux:subheading>Informasi lengkap unit fisik aset yang terdaftar.</flux:subheading>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Kode Register Aset</div>
                        <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->kode_aset }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nama Komoditas</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->barang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nomor Seri</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 font-mono">{{ $selectedAset->nomor_seri ?? '-' }}</div>
                    </div>

                    <div class="col-span-2 sm:col-span-3">
                        <div class="text-zinc-500 dark:text-zinc-400">Penempatan Lokasi</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                            @if ($selectedAset->unit_id)
                                Ruangan: {{ $selectedAset->unit?->nama }}
                            @elseif ($selectedAset->gudang_id)
                                Gudang: {{ $selectedAset->gudang?->nama }}
                                @if ($selectedAset->lokasiGudang)
                                    &middot; Rak: {{ $selectedAset->lokasiGudang->nama }}
                                @endif
                            @else
                                -
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Kondisi Fisik</div>
                        <div class="mt-0.5">
                            @php
                            $kondisiColor = match($selectedAset->kondisi) {
                                'BAIK' => 'emerald',
                                'RUSAK_RINGAN' => 'amber',
                                'RUSAK_BERAT' => 'rose',
                                default => 'zinc',
                            };
                            @endphp
                            <flux:badge size="sm" :color="$kondisiColor">{{ str_replace('_', ' ', $selectedAset->kondisi) }}</flux:badge>
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Status Operasional</div>
                        <div class="mt-0.5">
                            <x-status-badge :status="$selectedAset->status" />
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Histori Servis</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->permintaan_maintenance_count }} kali</div>
                    </div>

                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nilai Perolehan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                            <x-rupiah :value="$selectedAset->nilai_perolehan" />
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Tanggal Perolehan</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedAset->tanggal_perolehan->format('d/m/Y') }}</div>
                    </div>
                </div>

                {{-- Riwayat Sirkulasi Peminjaman Aset --}}
                <div class="pt-2">
                    <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200 mb-2 flex items-center justify-between">
                        <span>Buku Rekam Sirkulasi & Peminjaman Unit</span>
                        <span class="text-zinc-400 font-normal">{{ $selectedAset->peminjamanDetail->count() }} transaksi tercatat</span>
                    </div>

                    @if ($selectedAset->peminjamanDetail->isEmpty())
                        <div class="p-3 text-xs rounded-lg bg-zinc-50 dark:bg-zinc-800/40 text-zinc-500 italic text-center">
                            Belum pernah ada riwayat sirkulasi peminjaman untuk unit alat ini.
                        </div>
                    @else
                        @php
                            $latestD = $selectedAset->peminjamanDetail->sortByDesc('id')->first();
                            $latestPinjam = $latestD?->permintaanPeminjaman;
                            $latestKembali = $latestD?->pengembalianDetail->first();
                        @endphp
                        <div class="p-3 border border-zinc-200 dark:border-zinc-700/60 rounded-xl bg-zinc-50/60 dark:bg-zinc-800/30 space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">{{ $latestPinjam?->kode ?? '-' }}</span>
                                    <span class="text-[11px] text-zinc-400">&bull; Peminjaman Terakhir</span>
                                </div>
                                <x-status-badge :status="$latestPinjam?->status ?? \App\Enums\StatusPeminjaman::Selesai->value" />
                            </div>
                            <div class="text-xs text-zinc-700 dark:text-zinc-300">
                                <span class="font-semibold">{{ $latestPinjam?->unitPeminjam?->nama ?? 'Ruangan' }}</span>
                                <span class="text-zinc-400 font-normal">({{ $latestPinjam?->pengaju?->name ?? 'Staf' }})</span>
                            <div class="flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400 pt-1 border-t border-zinc-200/60 dark:border-zinc-700/40">
                                <span>Masa: {{ $latestPinjam?->tanggal_pinjam?->format('d/m/Y') ?? '-' }} s/d {{ $latestPinjam?->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}</span>
                                @if ($latestKembali)
                                    <span class="font-medium text-emerald-600 dark:text-emerald-400">Kembali: {{ str_replace('_', ' ', $latestKembali->kondisi_saat_kembali) }}</span>
                                @elseif ($latestPinjam?->status === \App\Enums\StatusPeminjaman::Dipinjam->value)
                                    <span class="font-medium text-amber-600 dark:text-amber-400">Sedang Dipinjam</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <flux:button size="xs" variant="primary" icon="arrow-top-right-on-square" wire:click="openSirkulasiModal({{ $selectedAset->id }})">
                                Lihat Semua Riwayat Sirkulasi ({{ $selectedAset->peminjamanDetail->count() }})
                            </flux:button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Buku Riwayat Sirkulasi Peminjaman Aset (Seperti Rekam Medis) --}}
    <flux:modal name="modal-riwayat-sirkulasi" wire:model="showSirkulasiModal" class="w-full max-w-5xl">
        @if ($selectedAset)
            <div class="space-y-5">
                {{-- Header Modal --}}
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Buku Rekam Sirkulasi & Peminjaman: {{ $selectedAset->barang?->nama }}</flux:heading>
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                                {{ $selectedAset->kode_aset }}
                            </span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Nomor Seri: {{ $selectedAset->nomor_seri ?? '-' }} &bull;
                            Penempatan: {{ $selectedAset->unit?->nama ?? $selectedAset->gudang?->nama ?? 'Sentral' }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2" style="margin-right: 2.25rem;">
                        <x-status-badge :status="$selectedAset->status" />
                    </div>
                </div>

                {{-- Ringkasan Statistik Sirkulasi --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Peminjaman</div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedAset->peminjamanDetail->count() }} Kali</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Status Operasional</div>
                        <div class="font-bold text-sm text-purple-600 dark:text-purple-400 mt-0.5">{{ $selectedAset->status }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Kondisi Fisik Terkini</div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">{{ str_replace('_', ' ', $selectedAset->kondisi) }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nilai Perolehan</div>
                        <div class="font-mono font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">
                            <x-rupiah :value="$selectedAset->nilai_perolehan" />
                        </div>
                    </div>
                </div>

                {{-- Tabel Kronologi Penuh --}}
                <div class="space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Linimasa Riwayat Peminjaman & Pengembalian Fisik</div>
                    <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700/60 rounded-xl">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold">
                                <tr>
                                    <th class="p-3">Kode Dokumen</th>
                                    <th class="p-3">Unit Peminjam</th>
                                    <th class="p-3">Masa Peminjaman</th>
                                    <th class="p-3">Keperluan</th>
                                    <th class="p-3 text-center">Status</th>
                                    <th class="p-3">Pengembalian Fisik</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/50">
                                @forelse ($selectedAset->peminjamanDetail->sortByDesc('id') as $d)
                                    @php
                                        $pinjam = $d->permintaanPeminjaman;
                                        $kembali = $d->pengembalianDetail->first();
                                    @endphp
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                        <td class="p-3 font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                            {{ $pinjam?->kode ?? '-' }}
                                        </td>
                                        <td class="p-3">
                                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $pinjam?->unitPeminjam?->nama ?? 'Ruangan' }}</div>
                                            <div class="text-zinc-500 text-[11px]">{{ $pinjam?->pengaju?->name ?? 'Staf' }}</div>
                                        <td class="p-3 text-zinc-700 dark:text-zinc-300">
                                            <div>{{ $pinjam?->tanggal_pinjam?->format('d/m/Y') ?? '-' }} s/d {{ $pinjam?->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}</div>
                                        </td>
                                        <td class="p-3 text-zinc-600 dark:text-zinc-400 max-w-xs truncate">
                                            {{ $pinjam?->keperluan ?: '-' }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <x-status-badge :status="$pinjam?->status ?? \App\Enums\StatusPeminjaman::Selesai->value" />
                                        </td>
                                        <td class="p-3">
                                            @if ($kembali)
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ str_replace('_', ' ', $kembali->kondisi_saat_kembali) }}</span>
                                                    <span class="text-[11px] text-zinc-400">&bull; {{ $kembali->pengembalianPeminjaman?->tanggal_kembali?->format('d/m/Y') ?? '-' }}</span>
                                                </div>
                                                @if ($kembali->catatan)
                                                    <div class="text-[11px] text-zinc-500 italic mt-0.5">"{{ $kembali->catatan }}"</div>
                                                @endif
                                            @elseif ($pinjam?->status === \App\Enums\StatusPeminjaman::Dipinjam->value)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                    Sedang Digunakan
                                                </span>
                                            @else
                                                <span class="text-zinc-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-zinc-500 italic">Belum ada data transaksi sirkulasi.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @endif
    </flux:modal>

    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
