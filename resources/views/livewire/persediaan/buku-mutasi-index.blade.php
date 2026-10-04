<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header Halaman --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Buku Mutasi & Kartu Stok Fisik</flux:heading>
                <flux:subheading>Log historis transaksi pergerakan barang persediaan rumah sakit (Masuk, Keluar, Penyesuaian Opname, dan Transfer).</flux:subheading>
            </div>

            @if ($filterJenis || $filterGudangId || $filterBarangId || $tanggalMulai || $tanggalSelesai || $search)
                <flux:button wire:click="resetFilters" size="xs" variant="ghost" icon="x-mark">
                    Reset Filter
                </flux:button>
            @endif
        </div>

        {{-- Toolbar Filter & Pencarian --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 pt-1">
            <div class="md:col-span-2">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari komoditas, nomor batch, keterangan..."
                    icon="magnifying-glass"
                    clearable
                />
            </div>

            <div>
                <select wire:model.live="filterJenis" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Jenis Mutasi</option>
                    <option value="MASUK">MASUK (Inbound PO / Retur)</option>
                    <option value="KELUAR">KELUAR (Amprah / Servis)</option>
                    <option value="PENYESUAIAN">PENYESUAIAN (Opname)</option>
                    <option value="TRANSFER">TRANSFER (Antar Rak / Gudang)</option>
                </select>
            </div>

            <div>
                <select wire:model.live="filterGudangId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Fasilitas Gudang</option>
                    @foreach ($daftarGudang as $gdg)
                        <option value="{{ $gdg->id }}">{{ $gdg->nama }} ({{ $gdg->kode }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <select wire:model.live="filterBarangId" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                    <option value="">Semua Komoditas</option>
                    @foreach ($daftarBarang as $brg)
                        <option value="{{ $brg->id }}">{{ $brg->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Baris Filter Tanggal --}}
        <div class="flex flex-wrap items-center gap-3 pt-1 text-xs text-zinc-600 dark:text-zinc-400">
            <span class="font-medium">Rentang Tanggal:</span>
            <div class="w-36">
                <input
                    type="date"
                    wire:model.live="tanggalMulai"
                    class="w-full h-8 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2 py-1 text-zinc-900 dark:text-zinc-100"
                />
            </div>
            <span>s/d</span>
            <div class="w-36">
                <input
                    type="date"
                    wire:model.live="tanggalSelesai"
                    class="w-full h-8 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2 py-1 text-zinc-900 dark:text-zinc-100"
                />
            </div>
        </div>

        {{-- Tabel Mutasi Stok --}}
        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-36">Waktu Mutasi</flux:table.column>
                <flux:table.column>Komoditas & Batch</flux:table.column>
                <flux:table.column>Gudang & Rak</flux:table.column>
                <flux:table.column class="text-center w-28">Jenis</flux:table.column>
                <flux:table.column class="text-right w-24">Jumlah</flux:table.column>
                <flux:table.column class="text-right w-36">Saldo (Lama &rarr; Baru)</flux:table.column>
                <flux:table.column>Keterangan & Referensi</flux:table.column>
                <flux:table.column class="text-right w-20">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($items as $item)
                    @php
                        $jenisColor = match($item->jenis) {
                            'MASUK'       => 'emerald',
                            'KELUAR'      => 'rose',
                            'PENYESUAIAN' => 'amber',
                            'TRANSFER'    => 'blue',
                            default       => 'zinc',
                        };
                    @endphp
                    <flux:table.row :key="$item->id">
                        {{-- Waktu --}}
                        <flux:table.cell class="text-xs tabular-nums text-zinc-600 dark:text-zinc-400">
                            <div>{{ $item->dibuat_pada?->format('d/m/Y') }}</div>
                            <div class="text-[10px] text-zinc-500 font-mono">{{ $item->dibuat_pada?->format('H:i:s') }}</div>
                        </flux:table.cell>

                        {{-- Komoditas & Batch --}}
                        <flux:table.cell>
                            <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->barang?->nama ?? '-' }}
                            </div>
                            <div class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Batch: {{ $item->stokBatch?->nomor_batch ?? '-' }}
                            </div>
                        </flux:table.cell>

                        {{-- Gudang & Rak --}}
                        <flux:table.cell>
                            <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $item->gudang?->nama ?? '-' }}
                            </div>
                            @if ($item->lokasiGudang)
                                <div class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400">
                                    Rak: {{ $item->lokasiGudang->nama }} ({{ $item->lokasiGudang->kode }})
                                </div>
                            @endif
                        </flux:table.cell>

                        {{-- Jenis Mutasi --}}
                        <flux:table.cell class="text-center">
                            <flux:badge size="sm" :color="$jenisColor">
                                {{ $item->jenis }}
                            </flux:badge>
                        </flux:table.cell>

                        {{-- Jumlah --}}
                        <flux:table.cell class="text-right font-mono font-bold text-xs tabular-nums {{ $item->jenis === 'MASUK' ? 'text-emerald-600 dark:text-emerald-400' : ($item->jenis === 'KELUAR' ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                            {{ $item->jenis === 'MASUK' ? '+' : ($item->jenis === 'KELUAR' ? '-' : '') }}{{ number_format($item->jumlah, 0, ',', '.') }}
                            <span class="text-[10px] font-normal font-sans text-zinc-500">{{ $item->barang?->satuan?->simbol ?? 'Unit' }}</span>
                        </flux:table.cell>

                        {{-- Saldo Sebelum & Sesudah --}}
                        <flux:table.cell class="text-right font-mono text-xs tabular-nums text-zinc-700 dark:text-zinc-300">
                            <span>{{ number_format($item->saldo_sebelum, 0, ',', '.') }}</span>
                            <span class="text-zinc-400 mx-1">&rarr;</span>
                            <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($item->saldo_sesudah, 0, ',', '.') }}</span>
                        </flux:table.cell>

                        {{-- Keterangan & Referensi --}}
                        <flux:table.cell>
                            <div class="text-xs text-zinc-800 dark:text-zinc-200 max-w-xs truncate">
                                {{ $item->keterangan ?: '-' }}
                            </div>
                            <div class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                                Ref: {{ $item->referensi_jenis }} #{{ $item->referensi_id }}
                            </div>
                        </flux:table.cell>

                        {{-- Aksi --}}
                        <flux:table.cell class="text-right">
                            <flux:button
                                wire:click="showDetail({{ $item->id }})"
                                size="xs"
                                variant="ghost"
                                icon="eye"
                                title="Lihat Rincian Mutasi"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada riwayat mutasi kartu stok yang tercatat." cols="8" />
                @endforelse
            </flux:table.rows>
        </flux:table>

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Detail Mutasi Stok --}}
    <flux:modal name="modal-detail-mutasi" wire:model="showDetailModal" class="md:w-[38rem]">
        @if ($selectedMutasi)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Mutasi Stok #{{ $selectedMutasi->id }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Waktu Transaksi: {{ $selectedMutasi->dibuat_pada?->format('d F Y H:i:s') }}
                        </flux:subheading>
                    </div>
                    @php
                        $mColor = match($selectedMutasi->jenis) {
                            'MASUK'       => 'emerald',
                            'KELUAR'      => 'rose',
                            'PENYESUAIAN' => 'amber',
                            'TRANSFER'    => 'blue',
                            default       => 'zinc',
                        };
                    @endphp
                    <div style="margin-right: 2.25rem;">
                        <flux:badge size="sm" :color="$mColor">{{ $selectedMutasi->jenis }}</flux:badge>
                    </div>
                </div>

                {{-- Metadata Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3.5 p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Komoditas Barang</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedMutasi->barang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nomor Batch</div>
                        <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedMutasi->stokBatch?->nomor_batch ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Fasilitas Gudang</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedMutasi->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Jumlah Bergerak</div>
                        <div class="font-bold text-sm font-mono text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ number_format($selectedMutasi->jumlah, 0, ',', '.') }} {{ $selectedMutasi->barang?->satuan?->simbol ?? 'Unit' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Saldo Fisik Sebelum</div>
                        <div class="font-bold text-sm font-mono text-zinc-700 dark:text-zinc-300 mt-0.5">
                            {{ number_format($selectedMutasi->saldo_sebelum, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Saldo Fisik Sesudah</div>
                        <div class="font-bold text-sm font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                            {{ number_format($selectedMutasi->saldo_sesudah, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Referensi Dokumen</div>
                        <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedMutasi->referensi_jenis }} #{{ $selectedMutasi->referensi_id }}
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-zinc-500 dark:text-zinc-400">Operator Pencatat</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            {{ $selectedMutasi->pembuatRecord?->nama ?? 'Sistem Otomatis' }}
                        </div>
                    </div>
                </div>

                @if ($selectedMutasi->keterangan)
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Keterangan Transaksi:</span>
                        <p class="text-zinc-600 dark:text-zinc-400 mt-0.5">{{ $selectedMutasi->keterangan }}</p>
                    </div>
                @endif

            </div>
        @endif
    </flux:modal>
</div>
