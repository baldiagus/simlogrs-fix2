<div class="space-y-6">
    {{-- Kartu Metrik Monitoring FEFO --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs cursor-pointer hover:border-rose-400 transition-colors" wire:click="setFilter('KEDALUWARSA')">
            <div class="flex items-center justify-between">
                <flux:text size="xs" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Telah Kedaluwarsa</flux:text>
                <flux:badge size="sm" color="rose" inset="top bottom">Kritis</flux:badge>
            </div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 font-mono tabular-nums">
                {{ $countExpired }} <span class="text-xs font-sans font-normal text-zinc-500">batch</span>
            </div>
            <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">Wajib karantina / retur vendor</flux:text>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs cursor-pointer hover:border-amber-400 transition-colors" wire:click="setFilter('KRITIS')">
            <div class="flex items-center justify-between">
                <flux:text size="xs" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Kritis (&lt; 30 Hari)</flux:text>
                <flux:badge size="sm" color="amber" inset="top bottom">Prioritas FEFO</flux:badge>
            </div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 font-mono tabular-nums">
                {{ $countKritis30 }} <span class="text-xs font-sans font-normal text-zinc-500">batch</span>
            </div>
            <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">Prioritaskan picking amprah segera</flux:text>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs cursor-pointer hover:border-blue-400 transition-colors" wire:click="setFilter('PERINGATAN')">
            <div class="flex items-center justify-between">
                <flux:text size="xs" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Peringatan (&lt; 90 Hari)</flux:text>
                <flux:badge size="sm" color="blue" inset="top bottom">Monitoring</flux:badge>
            </div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 font-mono tabular-nums">
                {{ $countPeringatan90 }} <span class="text-xs font-sans font-normal text-zinc-500">batch</span>
            </div>
            <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">Pantau rotasi pengeluaran barang</flux:text>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between">
                <flux:text size="xs" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Valuasi Kritis Tertahan</flux:text>
                <flux:badge size="sm" color="emerald" inset="top bottom">Rupiah</flux:badge>
            </div>
            <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100 font-mono">
                Rp {{ number_format($nilaiTertahanKritis, 0, ',', '.') }}
            </div>
            <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400">Total nilai stok batch &le; 90 hari</flux:text>
        </flux:card>
    </div>

    {{-- Tabel Utama Monitoring FEFO --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Monitoring Kedaluwarsa Persediaan (FEFO)</flux:heading>
                <flux:subheading>Pengawasan masa simpan fisik batch di rak gudang guna mencegah kerugian obat/barang kedaluwarsa.</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Tab Filter Cepat --}}
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
            <button
                wire:click="setFilter('KRITIS')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterKategoriKedaluwarsa === 'KRITIS' ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Kritis (&lt; 30 Hari)
            </button>
            <button
                wire:click="setFilter('PERINGATAN')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterKategoriKedaluwarsa === 'PERINGATAN' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Peringatan (&lt; 90 Hari)
            </button>
            <button
                wire:click="setFilter('KEDALUWARSA')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterKategoriKedaluwarsa === 'KEDALUWARSA' ? 'bg-white dark:bg-zinc-900 text-rose-600 dark:text-rose-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Kedaluwarsa
            </button>
            <button
                wire:click="setFilter('SEMUA')"
                type="button"
                class="px-3.5 py-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $filterKategoriKedaluwarsa === 'SEMUA' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
            >
                Semua Batch Aktif
            </button>
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari nomor batch, nama komoditas..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div class="w-full sm:w-56">
                <select wire:model.live="filterGudangId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Fasilitas Gudang</option>
                    @foreach ($daftarGudang as $gdg)
                        <option value="{{ $gdg->id }}">{{ $gdg->nama }} ({{ $gdg->kode }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tabel Batch FEFO --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-36">Nomor Batch</flux:table.column>
                <flux:table.column>Komoditas Barang</flux:table.column>
                <flux:table.column>Gudang & Rak Simpan</flux:table.column>
                <flux:table.column class="text-right w-28">Kuantitas</flux:table.column>
                <flux:table.column class="text-right w-36">Harga Satuan</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'tanggal_kedaluwarsa'" :direction="$sortDirection" wire:click="sort('tanggal_kedaluwarsa')" class="w-36">Kedaluwarsa (FEFO)</flux:table.column>
                <flux:table.column class="text-center w-32">Status Umur</flux:table.column>
                <flux:table.column class="text-right w-24">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    @php
                        $ed = $item->tanggal_kedaluwarsa;
                        $daysDiff = $ed ? (int) now()->startOfDay()->diffInDays($ed, false) : null;
                        
                        if ($daysDiff === null) {
                            $badgeText = 'Non-ED';
                            $badgeColor = 'zinc';
                        } elseif ($daysDiff < 0) {
                            $badgeText = 'Expired ' . abs($daysDiff) . ' hari';
                            $badgeColor = 'rose';
                        } elseif ($daysDiff <= 30) {
                            $badgeText = 'Sisa ' . $daysDiff . ' hari';
                            $badgeColor = 'rose';
                        } elseif ($daysDiff <= 90) {
                            $badgeText = 'Sisa ' . $daysDiff . ' hari';
                            $badgeColor = 'amber';
                        } else {
                            $badgeText = 'Aman (> 90 hari)';
                            $badgeColor = 'emerald';
                        }
                    @endphp
                    <flux:table.row :key="$item->id">
                        {{-- Nomor Batch --}}
                        <flux:table.cell class="font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $item->nomor_batch }}
                        </flux:table.cell>

                        {{-- Komoditas --}}
                        <flux:table.cell>
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->barang?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Kode: {{ $item->barang?->kode ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Gudang & Rak --}}
                        <flux:table.cell>
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $item->gudang?->nama ?? '-' }}
                            </div>
                            @if ($item->lokasiGudang)
                                <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    Rak: {{ $item->lokasiGudang->nama }} ({{ $item->lokasiGudang->kode }})
                                </div>
                            @else
                                <div class="text-[10px] text-zinc-400 italic mt-0.5">Tanpa penempatan rak khusus</div>
                            @endif
                        </flux:table.cell>

                        {{-- Kuantitas Fisik --}}
                        <flux:table.cell class="text-right font-mono font-bold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ number_format($item->jumlah, 0, ',', '.') }}
                            <span class="text-[10px] font-normal font-sans text-zinc-500">{{ $item->barang?->satuan?->simbol ?? 'Unit' }}</span>
                        </flux:table.cell>

                        {{-- Harga Satuan & Subtotal --}}
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                            <div><x-rupiah :value="$item->harga_satuan" /></div>
                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Total: Rp {{ number_format($item->jumlah * (float) $item->harga_satuan, 0, ',', '.') }}
                            </div>
                        </flux:table.cell>

                        {{-- Tanggal Kedaluwarsa --}}
                        <flux:table.cell class="text-xs tabular-nums">
                            @if ($item->tanggal_kedaluwarsa)
                                <div class="{{ $daysDiff <= 30 ? 'font-bold text-rose-600 dark:text-rose-400' : ($daysDiff <= 90 ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-zinc-700 dark:text-zinc-300') }}">
                                    {{ $item->tanggal_kedaluwarsa->format('d/m/Y') }}
                                </div>
                            @else
                                <span class="text-zinc-400 italic">Non-Expired</span>
                            @endif
                        </flux:table.cell>

                        {{-- Badge Status Umur --}}
                        <flux:table.cell class="text-center">
                            <flux:badge size="sm" :color="$badgeColor">
                                {{ $badgeText }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <flux:button
                                wire:click="showDetail({{ $item->id }})"
                                size="xs"
                                variant="ghost"
                                icon="eye"
                                title="Lihat Rincian Batch"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Tidak ada batch persediaan yang sesuai dengan kriteria monitoring." cols="8" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Detail Batch Persediaan --}}
    <flux:modal name="modal-detail-batch" wire:model="showDetailModal" class="md:w-[38rem]">
        @if ($selectedBatch)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Batch: {{ $selectedBatch->nomor_batch }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            {{ $selectedBatch->barang?->nama }} &bull; Kode: {{ $selectedBatch->barang?->kode }}
                        </flux:subheading>
                    </div>
                    <x-status-badge :status="$selectedBatch->status" />
                </div>

                {{-- Metadata Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3.5 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Fasilitas Gudang</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedBatch->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Lokasi Rak / Bin</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedBatch->lokasiGudang?->nama ?? 'Tanpa Rak' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Kategori Barang</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedBatch->barang?->kategori?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Saldo Fisik Tersedia</div>
                        <div class="font-bold text-sm font-mono text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ number_format($selectedBatch->jumlah, 0, ',', '.') }} {{ $selectedBatch->barang?->satuan?->simbol ?? 'Unit' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Jumlah Dipesan (Alokasi)</div>
                        <div class="font-bold text-sm font-mono text-amber-600 dark:text-amber-400 mt-0.5">
                            {{ number_format($selectedBatch->jumlah_dipesan, 0, ',', '.') }} {{ $selectedBatch->barang?->satuan?->simbol ?? 'Unit' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Harga Beli Satuan</div>
                        <div class="font-bold text-sm font-mono text-zinc-900 dark:text-zinc-100 mt-0.5">
                            <x-rupiah :value="$selectedBatch->harga_satuan" />
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-zinc-500 dark:text-zinc-400">Tanggal Kedaluwarsa (ED)</div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedBatch->tanggal_kedaluwarsa?->format('d F Y') ?? 'Tidak Berkedaluwarsa' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Nilai Persediaan</div>
                        <div class="font-bold text-sm font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                            Rp {{ number_format($selectedBatch->jumlah * (float) $selectedBatch->harga_satuan, 0, ',', '.') }}
                        </div>
                    </div>
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
