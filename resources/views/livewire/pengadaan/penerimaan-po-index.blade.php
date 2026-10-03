<div class="space-y-6">
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">Penerimaan Barang (Inbound)</flux:heading>
                <flux:subheading>Pantau status pengiriman PO vendor dan catat penerimaan fisik logistik.</flux:subheading>
            </div>
        </div>

        {{-- Tab Switcher (Dual-Mode: Tracking PO & Riwayat RCV) --}}
        <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-2">
            <button
                wire:click="switchTab('tracking')"
                type="button"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'tracking' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon.truck class="size-4" />
                <span>Menunggu Penerimaan (Tracking PO)</span>
                <span class="ml-1 text-xs px-2 py-0.5 rounded-full {{ $activeTab === 'tracking' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' }}">
                    {{ $countTracking }}
                </span>
            </button>

            <button
                wire:click="switchTab('riwayat')"
                type="button"
                class="flex items-center gap-2 pb-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'riwayat' ? 'border-blue-600 text-blue-600 dark:text-blue-400 font-semibold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon.inbox-arrow-down class="size-4" />
                <span>Riwayat Penerimaan (RCV)</span>
                <span class="ml-1 text-xs px-2 py-0.5 rounded-full {{ $activeTab === 'riwayat' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' }}">
                    {{ $countRiwayat }}
                </span>
            </button>
        </div>

        {{-- Filter Status Pills & Search Toolbar --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
            {{-- Filter Status Pills --}}
            <div class="flex flex-wrap items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl w-fit text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
                @if ($activeTab === 'tracking')
                    <button
                        wire:click="$set('filterStatus', '')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Semua
                    </button>
                    <button
                        wire:click="$set('filterStatus', 'DITERBITKAN')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'DITERBITKAN' ? 'bg-white dark:bg-zinc-900 text-blue-600 dark:text-blue-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Diterbitkan
                    </button>
                    <button
                        wire:click="$set('filterStatus', 'DIKIRIM_VENDOR')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'DIKIRIM_VENDOR' ? 'bg-white dark:bg-zinc-900 text-indigo-600 dark:text-indigo-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Dikirim Vendor
                    </button>
                    <button
                        wire:click="$set('filterStatus', 'DITERIMA_SEBAGIAN')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'DITERIMA_SEBAGIAN' ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Diterima Sebagian
                    </button>
                @else
                    <button
                        wire:click="$set('filterStatus', '')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === '' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Semua
                    </button>
                    <button
                        wire:click="$set('filterStatus', 'LENGKAP')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'LENGKAP' ? 'bg-white dark:bg-zinc-900 text-emerald-600 dark:text-emerald-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Lengkap
                    </button>
                    <button
                        wire:click="$set('filterStatus', 'SEBAGIAN')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'SEBAGIAN' ? 'bg-white dark:bg-zinc-900 text-amber-600 dark:text-amber-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Sebagian
                    </button>
                    <button
                        wire:click="$set('filterStatus', 'DITOLAK')"
                        type="button"
                        class="px-3.5 py-1.5 rounded-lg transition-colors {{ $filterStatus === 'DITOLAK' ? 'bg-white dark:bg-zinc-900 text-red-600 dark:text-red-400 shadow-xs font-semibold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        Ditolak
                    </button>
                @endif
            </div>

            {{-- Search Bar --}}
            <div class="w-full sm:w-80">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ $activeTab === 'tracking' ? 'Cari kode PO atau nama vendor...' : 'Cari kode RCV, surat jalan, atau kode PO...' }}"
                    icon="magnifying-glass"
                    clearable
                />
            </div>
        </div>

        {{-- Tabel Konten Berdasarkan Tab --}}
        @if ($activeTab === 'tracking')
            {{-- TAB 1: TABEL TRACKING PO --}}
            <flux:table bleed container:class="mt-2">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode PO</flux:table.column>
                    <flux:table.column>Vendor</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_po'" :direction="$sortDirection" wire:click="sort('tanggal_po')">Tanggal PO</flux:table.column>
                    <flux:table.column>Est. Kirim</flux:table.column>
                    <flux:table.column class="text-right">Total Biaya</flux:table.column>
                    <flux:table.column class="text-center w-40">Status PO</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($items as $item)
                        @php
                            $isLate = $item->estimasi_tanggal_kirim
                                && $item->estimasi_tanggal_kirim->isPast()
                                && $item->status !== 'DITERIMA_LENGKAP';
                        @endphp
                        <flux:table.row :key="'po-' . $item->id">
                            <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->pemasok?->nama ?? '-' }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs">{{ $item->tanggal_po?->format('d/m/Y') ?? '-' }}</flux:table.cell>
                            <flux:table.cell class="text-xs">
                                <span class="{{ $isLate ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                    {{ $item->estimasi_tanggal_kirim?->format('d/m/Y') ?? '-' }}
                                </span>
                                @if ($isLate)
                                    <flux:badge color="red" size="sm" class="ml-1">TERLAMBAT</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                                <x-rupiah :value="$item->total_biaya" class="font-semibold text-zinc-900 dark:text-zinc-100" />
                            </flux:table.cell>
                            <flux:table.cell class="text-center">
                                <x-status-badge :status="$item->status" />
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @can('create_gudang_stok::penerimaan::po')
                                        @if (in_array($item->status, ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN']))
                                            <flux:button
                                                wire:click="terima({{ $item->id }})"
                                                size="xs"
                                                variant="primary"
                                                icon="inbox-arrow-down"
                                                title="Terima Kiriman PO Ini"
                                            >
                                                Terima PO
                                            </flux:button>
                                        @endif
                                    @endcan
                                    <flux:button wire:click="showDetailPo({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail PO" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Tidak ada pesanan pembelian aktif yang menunggu penerimaan." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            {{-- TAB 2: TABEL RIWAYAT PENERIMAAN RCV --}}
            <flux:table bleed container:class="mt-2">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode RCV</flux:table.column>
                    <flux:table.column>Ref PO</flux:table.column>
                    <flux:table.column>Gudang</flux:table.column>
                    <flux:table.column>Surat Jalan</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_terima'" :direction="$sortDirection" wire:click="sort('tanggal_terima')">Tanggal</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                    <flux:table.column class="text-right w-28">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($items as $item)
                        <flux:table.row :key="'rcv-' . $item->id">
                            <flux:table.cell class="font-mono font-semibold text-xs">{{ $item->kode }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $item->pesananPembelian?->kode ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $item->gudang?->nama ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $item->nomor_surat_jalan }}</flux:table.cell>
                            <flux:table.cell class="text-xs">{{ $item->tanggal_terima?->format('d/m/Y') ?? '-' }}</flux:table.cell>
                            <flux:table.cell class="text-center">
                                <x-status-badge :status="$item->status" />
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <flux:button wire:click="showDetail({{ $item->id }})" size="xs" variant="ghost" icon="eye" title="Lihat Detail RCV" />
                                    @can('create_gudang_stok::penerimaan::po')
                                        @if ($item->pesananPembelian && $item->pesananPembelian->status === 'DITERIMA_SEBAGIAN')
                                            <flux:button
                                                wire:click="terima({{ $item->pesanan_pembelian_id }})"
                                                size="xs"
                                                variant="primary"
                                                icon="plus"
                                                title="Terima Sisa Barang PO ini"
                                            >
                                                Terima Sisa
                                            </flux:button>
                                        @endif
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Belum ada data penerimaan barang." cols="7" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif

        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Form Penerimaan PO --}}
    <flux:modal wire:model="showModal" class="w-full max-w-5xl" style="max-height: 88vh; overflow-y: auto;">
        <form wire:submit="savePenerimaan" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <flux:heading size="lg">Penerimaan Barang dari PO</flux:heading>
                    <flux:subheading>
                        @if ($po)
                            PO: <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $po->kode }}</span> - {{ $po->pemasok?->nama ?? '-' }}
                        @endif
                    </flux:subheading>
                </div>
                <div class="text-left sm:text-right">
                    <div class="text-[10px] font-medium text-zinc-700 dark:text-zinc-300">No. Penerimaan</div>
                    <div class="font-mono text-sm font-semibold text-zinc-900 dark:text-zinc-100 bg-zinc-100 dark:bg-zinc-800/80 px-2 py-0.5 rounded mt-0.5 border border-zinc-200 dark:border-zinc-700">
                        {{ $kode ?: 'OTOMATIS' }}
                    </div>
                </div>
            </div>

            {{-- Dokumen Header Input --}}
            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Nomor Surat Jalan <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model="nomor_surat_jalan"
                            placeholder="No. surat jalan vendor..."
                            required
                            class="w-full text-sm rounded-lg border {{ $errors->has('nomor_surat_jalan') ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm"
                        />
                        @error('nomor_surat_jalan') <p class="text-xs text-red-500 mt-1 flex items-center gap-1 font-medium">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-searchable-select
                            model="gudang_id"
                            label="Gudang Simpan"
                            placeholder="Pilih Fasilitas Gudang..."
                            :options="$daftarGudang"
                            live="true"
                            required
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                        Catatan Penerimaan <span class="text-zinc-500 dark:text-zinc-400 font-normal">(Opsional)</span>
                    </label>
                    <textarea
                        wire:model="catatan"
                        rows="2"
                        placeholder="misal: Kemasan kardus utuh, nomor segel vendor sesuai..."
                        class="w-full text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm resize-none"
                    ></textarea>
                </div>
            </div>

            {{-- Items Card List --}}
            @if (count($itemRows) > 0)
                <div class="space-y-2.5 pt-1">
                    <div class="flex items-center justify-between pb-1 border-b border-zinc-200 dark:border-zinc-800 text-xs">
                        <div class="font-semibold text-zinc-700 dark:text-zinc-300">
                            Daftar Komoditas Diterima ({{ count($itemRows) }} Item)
                        </div>
                        <div class="text-zinc-500 dark:text-zinc-400 text-[11px]">
                            Verifikasi data PO &bull; Catat fisik masuk & rak simpan
                        </div>
                    </div>

                    <div class="space-y-3 pb-48">
                        @foreach ($itemRows as $idx => $row)
                            <div wire:key="inbound-item-card-{{ $idx }}" class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm relative" style="overflow: visible; z-index: {{ 30 - $idx }};">
                                {{-- Baris 1: Ringkasan Pesanan (PO) --}}
                                <div class="px-3 py-2.5 bg-zinc-50/80 dark:bg-zinc-800/40 border-b border-zinc-200/80 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-t-xl">
                                    <div class="flex items-center gap-2.5">
                                        <div class="size-5 rounded-full bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300 flex items-center justify-center text-[10px] font-bold shrink-0">
                                            {{ $idx + 1 }}
                                        </div>
                                        <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $row['nama_barang'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-zinc-500">Sisa:</span>
                                            <span class="font-semibold px-2 py-0.5 rounded {{ $row['sisa'] > 0 ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/80 dark:text-amber-200' : 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-200' }}">
                                                {{ number_format($row['sisa'], 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                        <div class="text-zinc-500">Pesan: <span class="font-medium text-zinc-700 dark:text-zinc-300 tabular-nums">{{ number_format($row['jumlah_dipesan'], 0, ',', '.') }}</span></div>
                                        <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                        <div class="text-zinc-500">Terima: <span class="font-medium text-zinc-700 dark:text-zinc-300 tabular-nums">{{ number_format($row['jumlah_sudah'], 0, ',', '.') }}</span></div>
                                    </div>
                                </div>

                                {{-- Baris 2: Input Fisik Penerimaan --}}
                                @if ($row['sisa'] <= 0)
                                    <div class="p-3 bg-zinc-50/60 dark:bg-zinc-800/30 text-center text-xs text-emerald-700 dark:text-emerald-400 font-medium flex items-center justify-center gap-1.5">
                                        <flux:icon icon="check-circle" class="size-4 text-emerald-600" />
                                        <span>Komoditas ini sudah terpenuhi lengkap ({{ number_format($row['jumlah_sudah'], 0, ',', '.') }}) pada penerimaan sebelumnya.</span>
                                    </div>
                                @else
                                    <div class="p-3 bg-white dark:bg-zinc-900">
                                        <div class="grid grid-cols-2 gap-3 items-start">
                                            <div class="flex flex-col">
                                                <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                                                    Jml Terima <span class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="number"
                                                    wire:model="itemRows.{{ $idx }}.jumlah_diterima"
                                                    min="0"
                                                    max="{{ $row['sisa'] }}"
                                                    placeholder="Maks: {{ $row['sisa'] }}"
                                                    class="w-full text-right font-mono tabular-nums text-sm rounded-md border {{ $errors->has("itemRows.{$idx}.jumlah_diterima") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-shadow"
                                                />
                                                @error("itemRows.{$idx}.jumlah_diterima") <span class="text-red-500 text-[10px] mt-0.5 leading-tight font-medium">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="flex flex-col">
                                                <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                                                    Jml Rusak
                                                </label>
                                                <input
                                                    type="number"
                                                    wire:model="itemRows.{{ $idx }}.jumlah_rusak"
                                                    min="0"
                                                    placeholder="0"
                                                    class="w-full text-right font-mono tabular-nums text-sm rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-shadow"
                                                />
                                                @error("itemRows.{$idx}.jumlah_rusak") <span class="text-red-500 text-[10px] mt-0.5 leading-tight">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="flex flex-col">
                                                <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                                                    No. Batch <span class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    wire:model="itemRows.{{ $idx }}.nomor_batch"
                                                    placeholder="Batch pabrik..."
                                                    class="w-full font-mono uppercase text-sm rounded-md border {{ $errors->has("itemRows.{$idx}.nomor_batch") ? 'border-red-500 dark:border-red-500 ring-1 ring-red-500/30' : 'border-zinc-300 dark:border-zinc-700' }} bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-shadow"
                                                />
                                                @error("itemRows.{$idx}.nomor_batch") <span class="text-red-500 text-[10px] mt-0.5 leading-tight font-medium">{{ $message }}</span> @enderror
                                            </div>

                                            <div class="flex flex-col">
                                                <label class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 mb-1">
                                                    Kedaluwarsa (ED)
                                                </label>
                                                <input
                                                    type="date"
                                                    wire:model="itemRows.{{ $idx }}.tanggal_kedaluwarsa"
                                                    class="w-full text-sm rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2.5 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-shadow text-zinc-600 dark:text-zinc-400"
                                                />
                                            </div>

                                            <div class="col-span-2" wire:key="rak-select-wrapper-{{ $gudang_id ?? 'empty' }}-{{ $idx }}">
                                                <x-searchable-select
                                                    model="itemRows.{{ $idx }}.lokasi_gudang_id"
                                                    label="Rak Simpan"
                                                    placeholder="Pilih Rak..."
                                                    :options="$daftarLokasiGudang"
                                                    required
                                                />
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="savePenerimaan">Simpan Penerimaan</span>
                    <span wire:loading wire:target="savePenerimaan">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Detail PO (Tracking Detail) --}}
    <flux:modal name="modal-tracking-detail" wire:model="showDetailPoModal" class="md:w-[48rem]">
        @if ($selectedPo)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Detail PO: {{ $selectedPo->kode }}</flux:heading>
                    <flux:subheading>Informasi lengkap pesanan pembelian dan status pengiriman vendor.</flux:subheading>
                </div>

                {{-- Info Ringkas --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400 font-medium">Vendor</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPo->pemasok?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400 font-medium">Tanggal PO</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPo->tanggal_po?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400 font-medium">Est. Kirim</div>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPo->estimasi_tanggal_kirim?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400 font-medium">Status PO</div>
                        <div><x-status-badge :status="$selectedPo->status" /></div>
                    </div>
                </div>

                @php
                    $isLate = $selectedPo->estimasi_tanggal_kirim
                        && $selectedPo->estimasi_tanggal_kirim->isPast()
                        && $selectedPo->status !== 'DITERIMA_LENGKAP';
                @endphp
                @if ($isLate)
                    <div class="flex items-center gap-2 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
                        <flux:icon.exclamation-triangle class="size-5" />
                        <span class="font-semibold">Pengiriman terlambat: estimasi kirim {{ $selectedPo->estimasi_tanggal_kirim->format('d/m/Y') }} sudah terlewat.</span>
                    </div>
                @endif

                {{-- Daftar Item --}}
                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Daftar Item Pesanan</div>
                <flux:table bleed>
                    <flux:table.columns>
                        <flux:table.column>Barang</flux:table.column>
                        <flux:table.column class="text-right">Qty Pesan</flux:table.column>
                        <flux:table.column class="text-right">Qty Diterima</flux:table.column>
                        <flux:table.column class="text-right">Harga Satuan</flux:table.column>
                        <flux:table.column class="text-right">Subtotal</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($selectedPo->detail as $detail)
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $detail->barang?->kode ?? '' }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="text-right font-mono text-xs tabular-nums">{{ number_format($detail->jumlah_dipesan, 0, ',', '.') }}</flux:table.cell>
                                <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                                    @if ($detail->jumlah_diterima >= $detail->jumlah_dipesan)
                                        <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ number_format($detail->jumlah_diterima, 0, ',', '.') }}</span>
                                    @else
                                        <span class="{{ $detail->jumlah_diterima > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500 dark:text-zinc-400' }}">{{ number_format($detail->jumlah_diterima, 0, ',', '.') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                                    <x-rupiah :value="$detail->harga_satuan" />
                                </flux:table.cell>
                                <flux:table.cell class="text-right font-mono text-xs tabular-nums">
                                    <x-rupiah :value="$detail->subtotal" class="font-semibold" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-empty-state message="Tidak ada item dalam PO ini." cols="5" />
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                {{-- Total Biaya --}}
                <div class="flex justify-end p-3 rounded-lg bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800">
                    <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                        Total: <x-rupiah :value="$selectedPo->total_biaya" class="text-blue-600 dark:text-blue-400" />
                    </div>
                </div>

                {{-- Timeline / Riwayat Penerimaan Barang --}}
                @if ($selectedPo->penerimaanBarang && $selectedPo->penerimaanBarang->isNotEmpty())
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Riwayat Penerimaan Fisik</div>
                    <div class="space-y-2">
                        @foreach ($selectedPo->penerimaanBarang as $penerimaan)
                            <div class="flex items-start gap-3 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800 text-xs">
                                <flux:icon.inbox-arrow-down class="size-4 text-emerald-500 mt-0.5 shrink-0" />
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $penerimaan->kode ?? 'Penerimaan' }}</div>
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ $penerimaan->tanggal_terima?->format('d/m/Y') ?? '-' }} &bull; Surat Jalan: {{ $penerimaan->nomor_surat_jalan }}</div>
                                    <div class="text-zinc-500 mt-0.5"><x-status-badge :status="$penerimaan->status" /></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailPoModal', false)">Tutup</flux:button>
                    @can('create_gudang_stok::penerimaan::po')
                        @if (in_array($selectedPo->status, ['DIKIRIM_VENDOR', 'DITERIMA_SEBAGIAN']))
                            <flux:button
                                type="button"
                                variant="primary"
                                icon="inbox-arrow-down"
                                wire:click="terima({{ $selectedPo->id }})"
                                x-on:click="$flux.modal('modal-tracking-detail').close()"
                            >
                                Terima PO Ini
                            </flux:button>
                        @endif
                    @endcan
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Detail Penerimaan RCV --}}
    <flux:modal wire:model="showDetailModal" class="w-full max-w-[92vw] lg:max-w-6xl">
        @if ($selectedPenerimaan)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Detail Penerimaan: {{ $selectedPenerimaan->kode }}</flux:heading>
                    <flux:subheading>Ref PO: {{ $selectedPenerimaan->pesananPembelian?->kode ?? '-' }} - {{ $selectedPenerimaan->pesananPembelian?->pemasok?->nama ?? '-' }}</flux:subheading>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-zinc-500">Gudang</div>
                        <div class="font-medium">{{ $selectedPenerimaan->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Surat Jalan</div>
                        <div class="font-medium">{{ $selectedPenerimaan->nomor_surat_jalan }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Tanggal Terima</div>
                        <div class="font-medium">{{ $selectedPenerimaan->tanggal_terima?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Diterima Oleh</div>
                        <div class="font-medium">{{ $selectedPenerimaan->penerima?->nama ?? '-' }}</div>
                    </div>
                </div>

                @if ($selectedPenerimaan->catatan)
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-sm">
                        <span class="font-semibold text-xs text-zinc-500 block mb-0.5">Catatan</span>
                        {{ $selectedPenerimaan->catatan }}
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-zinc-500">
                                <th class="pb-2 font-semibold">Barang</th>
                                <th class="pb-2 font-semibold text-center">Diterima</th>
                                <th class="pb-2 font-semibold text-center">Rusak</th>
                                <th class="pb-2 font-semibold text-center">Nett</th>
                                <th class="pb-2 font-semibold">Batch</th>
                                <th class="pb-2 font-semibold">Kedaluwarsa</th>
                                <th class="pb-2 font-semibold">Rak</th>
                                <th class="pb-2 font-semibold text-right">Harga Satuan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($selectedPenerimaan->detail as $det)
                                <tr>
                                    <td class="py-2 pr-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $det->barang?->nama ?? '-' }}</td>
                                    <td class="py-2 text-center font-mono">{{ number_format($det->jumlah_diterima, 0, ',', '.') }}</td>
                                    <td class="py-2 text-center font-mono {{ $det->jumlah_rusak > 0 ? 'text-red-500 font-semibold' : '' }}">{{ number_format($det->jumlah_rusak, 0, ',', '.') }}</td>
                                    <td class="py-2 text-center font-mono font-semibold text-emerald-600">{{ number_format($det->jumlah_diterima - $det->jumlah_rusak, 0, ',', '.') }}</td>
                                    <td class="py-2 font-mono">{{ $det->nomor_batch }}</td>
                                    <td class="py-2">{{ $det->tanggal_kedaluwarsa?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="py-2">{{ $det->lokasiGudang?->nama ?? $det->lokasiGudang?->kode ?? '-' }}</td>
                                    <td class="py-2 text-right"><x-rupiah :value="$det->harga_satuan" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="$set('showDetailModal', false)">Tutup</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
