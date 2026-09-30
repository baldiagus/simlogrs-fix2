<div class="space-y-6">
    {{-- Ringkasan Metrik Persediaan Ruangan --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400">
                <flux:icon name="cube" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total Jenis Barang</div>
                <div class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ $countTotalItem }} Item</div>
            </div>
        </flux:card>

        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="check-circle" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Persediaan Tersedia (&gt; 0)</div>
                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $countTersedia }} Item</div>
            </div>
        </flux:card>
        <flux:card class="p-4 border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-center gap-4">
            <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400">
                <flux:icon name="x-circle" class="w-6 h-6" />
            </div>
            <div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Persediaan Habis (Kosong)</div>
                <div class="text-xl font-bold text-rose-600 dark:text-rose-400">{{ $countHabis }} Item</div>
            </div>
        </flux:card>
    </div>

    {{-- Kartu Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $pageTitle }}</flux:heading>
                <flux:subheading>Monitoring sisa fisik barang di lemari ruangan dan pencatatan pemakaian operasional/pelayanan.</flux:subheading>
            </div>
            <div>
                <flux:button as="a" href="/{{ request()->segment(1) ?? 'unit' }}/permintaan-barang" variant="primary" icon="plus">
                    Ajukan Amprah Baru
                </flux:button>
            </div>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center gap-3">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode barang, nama, unit..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-48">
                <select wire:model.live="filterKondisi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Kondisi Stok</option>
                    <option value="TERSEDIA">Tersedia (&gt; 0)</option>
                    <option value="HABIS">Habis (0)</option>
                </select>
            </div>

            @if ($isGlobalAdmin && $daftarUnit->isNotEmpty())
                <div class="w-full sm:w-64">
                    <select wire:model.live="filterUnitId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                        <option value="">-- Semua Unit / Ruangan --</option>
                        @foreach ($daftarUnit as $u)
                            <option value="{{ $u->id }}">{{ $u->nama }} ({{ $u->kode }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        {{-- Tabel Persediaan Lokal Ruangan --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column sortable :sorted="$sortBy === 'barang_id'" :direction="$sortDirection" wire:click="sort('barang_id')" class="w-36">Kode</flux:table.column>
                <flux:table.column>Nama Komoditas & Kategori</flux:table.column>
                @if ($isGlobalAdmin)
                    <flux:table.column>Unit / Ruangan</flux:table.column>
                @endif
                <flux:table.column sortable :sorted="$sortBy === 'jumlah'" :direction="$sortDirection" wire:click="sort('jumlah')" class="text-right w-36">Sisa Fisik</flux:table.column>
                <flux:table.column class="text-center w-32">Status</flux:table.column>
                <flux:table.column class="text-right w-44">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($daftarStok as $stok)
                    @php
                        $satuan = $stok->barang?->satuan?->simbol ?? 'Pcs';
                        $isHabis = $stok->jumlah <= 0;
                    @endphp
                    <flux:table.row :key="$stok->id">
                        {{-- Kode Barang --}}
                        <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $stok->barang?->kode ?? '-' }}
                        </flux:table.cell>

                        {{-- Nama & Kategori --}}
                        <flux:table.cell>
                            <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $stok->barang?->nama ?? 'Komoditas' }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Kategori: {{ $stok->barang?->kategori?->nama ?? 'Umum' }}
                            </div>
                        </flux:table.cell>

                        {{-- Unit (Jika Admin) --}}
                        @if ($isGlobalAdmin)
                            <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">
                                {{ $stok->unit?->nama ?? '-' }}
                            </flux:table.cell>
                        @endif

                        {{-- Sisa Fisik --}}
                        <flux:table.cell class="text-right">
                            <span class="font-bold text-sm {{ $isHabis ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ number_format($stok->jumlah, 0, ',', '.') }}
                            </span>
                            <span class="text-xs text-zinc-500 ml-1">{{ $satuan }}</span>
                        </flux:table.cell>

                        {{-- Status Badge --}}
                        <flux:table.cell class="text-center">
                            @if ($isHabis)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-800">
                                    HABIS
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                    TERSEDIA
                                </span>
                            @endif
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if (! $isHabis)
                                    <flux:button
                                        wire:click="openCatatPemakaian({{ $stok->id }})"
                                        size="xs"
                                        variant="primary"
                                        icon="minus-circle"
                                        title="Catat Pemakaian"
                                    >
                                        Catat Pakai
                                    </flux:button>
                                @else
                                    <flux:button
                                        as="a"
                                        href="/{{ request()->segment(1) ?? 'unit' }}/permintaan-barang"
                                        size="xs"
                                        variant="danger"
                                        icon="arrow-up-tray"
                                        title="Stok Habis - Ajukan Amprah"
                                    >
                                        Amprah
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada persediaan barang yang tercatat di lemari ruangan ini. Lakukan amprah dan konfirmasi terima barang terlebih dahulu." cols="7" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{-- Pagination --}}
        <x-table-pagination :paginator="$daftarStok" />
    </flux:card>

    {{-- Modal Catat Pemakaian Barang --}}
    <flux:modal name="modal-catat-pemakaian" wire:model="showPakaiModal" class="md:w-[32rem]">
        @if ($selectedStok)
            <form wire:submit="simpanPemakaian" class="space-y-4">
                <div>
                    <flux:heading size="lg">Catat Pemakaian Barang</flux:heading>
                    <flux:subheading>Kurangi sisa fisik stok di lemari ruangan saat digunakan untuk operasional atau pelayanan pasien.</flux:subheading>
                </div>

                {{-- Detail Barang Terpilih --}}
                <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/60 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Nama Barang:</span>
                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $selectedStok->barang?->nama }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Unit / Ruangan:</span>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $selectedStok->unit?->nama }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-500">Sisa Fisik Saat Ini:</span>
                        <span class="font-bold text-purple-600 dark:text-purple-400 text-sm">
                            {{ $selectedStok->jumlah }} {{ $selectedStok->barang?->satuan?->simbol ?? 'Pcs' }}
                        </span>
                    </div>
                </div>

                {{-- Input Kuantitas Pemakaian --}}
                <div>
                    <flux:input
                        wire:model="jumlah_pakai"
                        type="number"
                        min="1"
                        max="{{ $selectedStok->jumlah }}"
                        label="Kuantitas yang Digunakan / Habis"
                        placeholder="Masukkan jumlah yang dipakai"
                        required
                    />
                    <div class="text-[11px] text-zinc-500 mt-1">
                        Maksimal pemakaian: {{ $selectedStok->jumlah }} {{ $selectedStok->barang?->satuan?->simbol ?? 'Pcs' }}
                    </div>
                </div>

                {{-- Keterangan / Keperluan --}}
                <div>
                    <flux:textarea
                        wire:model="keterangan"
                        label="Keterangan / Keperluan Pemakaian"
                        placeholder="Contoh: Operasional pelayanan IGD shift pagi / pembersihan ruang rawat..."
                        rows="3"
                        required
                    />
                </div>

                {{-- Tombol Aksi Modal --}}
                <div class="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="$set('showPakaiModal', false)">
                        Batal
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="simpanPemakaian">Simpan Pemakaian</span>
                        <span wire:loading wire:target="simpanPemakaian">Memproses...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
