@use('App\Enums\StatusPermintaanDistribusi')
<div class="space-y-6">
    {{-- Header Halaman & Sub-Navigasi --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Picking FEFO & Pengeluaran Barang (SPPB)</flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">Alokasi pengeluaran stok otomatis berbasis First Expired, First Out (FEFO) dan penerbitan Surat Perintah Pengeluaran Barang (SPPB).</flux:subheading>
            </div>
        </div>

        {{-- Navigasi Sub-Tab Penyaluran SPPB --}}
        <x-distribusi-sppb-tabs active="picking" />
    </div>

    {{-- Workspace Card Utama --}}
    <flux:card class="space-y-5 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        {{-- Navigasi Tab --}}
        <div class="flex flex-wrap items-center justify-between gap-4 pt-1">
            <div class="flex items-center gap-1.5 p-1 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl text-xs font-medium border border-zinc-200/60 dark:border-zinc-700/60">
                <button
                    type="button"
                    wire:click="setTab('siap-picking')"
                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg transition-colors {{ $activeTab === 'siap-picking' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-bold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    <span>Siap Picking</span>
                    @if ($siapPickingCount > 0)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/60 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60">
                            {{ $siapPickingCount }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">
                            0
                        </span>
                    @endif
                </button>
                <button
                    type="button"
                    wire:click="setTab('riwayat-sppb')"
                    class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg transition-colors {{ $activeTab === 'riwayat-sppb' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs font-bold' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    <span>Riwayat SPPB</span>
                </button>
            </div>

            {{-- Toolbar Filter & Pencarian --}}
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-80">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ $activeTab === 'siap-picking' ? 'Cari kode amprah, unit, barang...' : 'Cari kode SPPB, ref amprah, unit...' }}"
                        icon="magnifying-glass"
                        clearable
                    />
                </div>

                @if ($activeTab === 'siap-picking')
                    <div class="w-full sm:w-44">
                        <select wire:model.live="filterUrgensi" class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100">
                            <option value="">Semua Urgensi</option>
                            <option value="REGULER">REGULER</option>
                            <option value="CITO">CITO (Darurat)</option>
                        </select>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tabel Mode 1: Antrean Siap Picking --}}
        @if ($activeTab === 'siap-picking')
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-32">Kode Amprah</flux:table.column>
                    <flux:table.column class="w-48">Unit</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_permintaan'" :direction="$sortDirection" wire:click="sort('tanggal_permintaan')" class="w-28">Tanggal</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'urgensi'" :direction="$sortDirection" wire:click="sort('urgensi')" class="text-center w-24">Urgensi</flux:table.column>
                    <flux:table.column class="w-56">Item</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($items as $item)
                        <flux:table.row :key="$item->id">
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                <div>{{ $item->kode }}</div>
                                @if ($item->status === StatusPermintaanDistribusi::DikirimSebagian->value)
                                    <flux:badge size="xs" color="amber" class="mt-0.5">Sisa Terhutang</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell style="white-space: normal;">
                                <div class="font-medium text-xs text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Gudang: {{ $item->gudang?->nama ?? '-' }}</div>
                            </flux:table.cell>

                            <flux:table.cell class="text-xs tabular-nums">
                                {{ $item->tanggal_permintaan?->format('d/m/Y') ?? '-' }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <x-urgency-badge :urgency="$item->urgensi" />
                            </flux:table.cell>

                            <flux:table.cell style="white-space: normal;">
                                <div class="text-xs">
                                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $item->detail_count ?? $item->detail->count() }} item</span>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 line-clamp-1" title="{{ $item->detail->map(fn ($d) => ($d->barang?->nama ?? 'Barang') . ' (' . ($d->jumlah_disetujui ?: $d->jumlah) . ' ' . ($d->barang?->satuan?->simbol ?? $d->barang?->satuan?->nama ?? 'unit') . ')')->implode(', ') }}">
                                        {{ $item->detail->map(fn ($d) => ($d->barang?->nama ?? 'Barang') . ' (' . ($d->jumlah_disetujui ?: $d->jumlah) . ' ' . ($d->barang?->satuan?->simbol ?? $d->barang?->satuan?->nama ?? 'unit') . ')')->implode(', ') }}
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="showPermintaanDetail({{ $item->id }})" icon="eye">
                                            Detail Permintaan
                                        </flux:menu.item>

                                        @canany(['create_gudang_stok::picking::distribusi'])
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="confirmPicking({{ $item->id }})" icon="cube" class="text-emerald-600 dark:text-emerald-400">
                                                {{ $item->status === StatusPermintaanDistribusi::DikirimSebagian->value ? 'Picking Sisa Hutang' : 'Eksekusi Picking FEFO' }}
                                            </flux:menu.item>
                                        @endcanany
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Tidak ada antrean amprah yang siap dipicking." :cols="6" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @else
            {{-- Tabel Mode 2: Riwayat SPPB Terbit --}}
            <flux:table bleed container:class="mt-4">
                <flux:table.columns sticky>
                    <flux:table.column sortable :sorted="$sortBy === 'kode'" :direction="$sortDirection" wire:click="sort('kode')" class="w-36">Kode SPPB</flux:table.column>
                    <flux:table.column>Ref Amprah</flux:table.column>
                    <flux:table.column>Unit Tujuan</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'tanggal_kirim'" :direction="$sortDirection" wire:click="sort('tanggal_kirim')">Tanggal Kirim</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')" class="text-center w-28">Status</flux:table.column>
                    <flux:table.column class="text-right w-36">Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($items as $item)
                        <flux:table.row :key="$item->id">
                            <flux:table.cell class="font-mono font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $item->kode }}
                            </flux:table.cell>

                            <flux:table.cell class="font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                {{ $item->permintaanDistribusi?->kode ?? '-' }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->unit?->nama ?? '-' }}</div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">Gudang: {{ $item->gudang?->nama ?? '-' }}</div>
                            </flux:table.cell>

                            <flux:table.cell class="text-xs tabular-nums">
                                {{ $item->tanggal_kirim?->format('d/m/Y') ?? '-' }}
                            </flux:table.cell>

                            <flux:table.cell class="text-center">
                                <x-status-badge :status="$item->status" />
                            </flux:table.cell>

                            <flux:table.cell class="text-right w-16">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" title="Menu Opsi" />
                                    <flux:menu class="min-w-44">
                                        <flux:menu.item wire:click="showSppbDetail({{ $item->id }})" icon="eye">
                                            Detail Alokasi Batch
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <x-empty-state message="Belum ada riwayat SPPB yang diterbitkan." :cols="6" />
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif

        {{-- Pagination --}}
        <x-table-pagination :paginator="$items" />
    </flux:card>

    {{-- Modal Detail Alokasi Batch FEFO --}}
    <flux:modal wire:model="showDetailModal" class="w-full max-w-[92vw] lg:max-w-5xl">
        @if ($selectedSppb)
            <div class="space-y-4">
                <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div>
                        <flux:heading size="lg">Detail Alokasi SPPB: {{ $selectedSppb->kode }}</flux:heading>
                        <flux:subheading>
                            Ref Amprah: <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->permintaanDistribusi?->kode ?? '-' }}</span> &bull;
                            Unit Tujuan: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->unit?->nama ?? '-' }}</span>
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-status-badge :status="$selectedSppb->status" />
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg">
                    <div>
                        <span class="text-zinc-500">Gudang Pengirim:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Tanggal Pengiriman:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->tanggal_kirim?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Petugas Picking:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->pengirim?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Total Kuantitas:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($selectedSppb->detail->sum('jumlah_dikirim'), 0, ',', '.') }} unit</div>
                    </div>
                </div>

                @if ($selectedSppb->catatan)
                    <div class="text-xs text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800/80 p-2.5 rounded-md">
                        <span class="font-semibold">Catatan:</span> {{ $selectedSppb->catatan }}
                    </div>
                @endif

                {{-- Tabel Rincian Batch FEFO --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-xs">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700 text-left font-semibold">
                            <tr>
                                <th class="py-2.5 px-3 w-10 text-center">No</th>
                                <th class="py-2.5 px-3">Nama Komoditas</th>
                                <th class="py-2.5 px-3">Nomor Batch</th>
                                <th class="py-2.5 px-3">Kedaluwarsa (FEFO)</th>
                                <th class="py-2.5 px-3">Lokasi Rak / Bin</th>
                                <th class="py-2.5 px-3 text-right">Kuantitas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($selectedSppb->detail as $idx => $det)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2 px-3 text-center text-zinc-500 dark:text-zinc-400">{{ $idx + 1 }}</td>
                                    <td class="py-2 px-3 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $det->barang?->nama ?? '-' }}
                                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $det->barang?->kode ?? '' }}</div>
                                    </td>
                                    <td class="py-2 px-3 font-mono font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ $det->stokBatch?->nomor_batch ?? '-' }}
                                    </td>
                                    <td class="py-2 px-3">
                                        @if ($det->stokBatch?->tanggal_kedaluwarsa)
                                            <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 font-medium">
                                                {{ $det->stokBatch->tanggal_kedaluwarsa->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="text-zinc-500 dark:text-zinc-400 italic">Non-Expired</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        @if ($det->stokBatch?->lokasiGudang)
                                            <span class="inline-flex items-center gap-1 font-mono text-xs">
                                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $det->stokBatch->lokasiGudang->nama }}</span>
                                                <span class="text-zinc-500 dark:text-zinc-400">({{ $det->stokBatch->lokasiGudang->kode }})</span>
                                            </span>
                                        @else
                                            <span class="text-zinc-500 dark:text-zinc-400">-</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($det->jumlah_dikirim, 0, ',', '.') }}
                                        <span class="text-xs font-normal text-zinc-500 font-sans">{{ $det->barang?->satuan?->simbol ?? $det->barang?->satuan?->nama ?? '' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-zinc-500 dark:text-zinc-400">Tidak ada detail alokasi batch.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        @endif
    </flux:modal>

    {{-- Modal Detail Permintaan Amprah Siap Picking --}}
    <flux:modal name="modal-permintaan-picking-detail" wire:model="showPermintaanDetailModal" class="w-full max-w-[92vw] lg:max-w-5xl">
        @if ($selectedPermintaanDetail)
            <div class="space-y-4">
                <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div>
                        <flux:heading size="lg">Detail Rencana Picking: {{ $selectedPermintaanDetail->kode }}</flux:heading>
                        <flux:subheading>
                            Unit Pemohon: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->unit?->nama ?? '-' }}</span> &bull;
                            Gudang Penyedia: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->gudang?->nama ?? '-' }}</span>
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-status-badge :status="$selectedPermintaanDetail->status" />
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg">
                    <div>
                        <span class="text-zinc-500">Tanggal Pengajuan:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->tanggal_permintaan?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Tingkat Urgensi:</span>
                        <div class="mt-0.5"><x-urgency-badge :urgency="$selectedPermintaanDetail->urgensi" /></div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Pemohon / Karu:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->pengaju?->nama ?? '-' }} / {{ $selectedPermintaanDetail->penyetuju?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Total Komoditas:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaanDetail->detail->count() }} Jenis Barang</div>
                    </div>
                </div>

                @if ($selectedPermintaanDetail->catatan)
                    <div class="text-xs text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800/80 p-2.5 rounded-md">
                        <span class="font-semibold">Catatan Pemohon:</span> {{ $selectedPermintaanDetail->catatan }}
                    </div>
                @endif

                {{-- Tabel Rincian Komoditas yang Mau di-Picking --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-xs">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700 text-left font-semibold">
                            <tr>
                                <th class="py-2.5 px-3 w-10 text-center">No</th>
                                <th class="py-2.5 px-3">Nama Komoditas</th>
                                <th class="py-2.5 px-3 text-center">Satuan</th>
                                <th class="py-2.5 px-3 text-right">Diminta</th>
                                <th class="py-2.5 px-3 text-right">Disetujui</th>
                                <th class="py-2.5 px-3 text-right">Terkirim</th>
                                <th class="py-2.5 px-3 text-right">Sisa Mau Dipicking</th>
                                <th class="py-2.5 px-3 text-right">Stok Fisik Bebas</th>
                                <th class="py-2.5 px-3">Catatan Item</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($selectedPermintaanDetail->detail as $idx => $det)
                                @php
                                    $info = $ringkasanPermintaanDetail[$det->id] ?? [
                                        'diminta' => (int) $det->jumlah,
                                        'disetujui' => (int) ($det->jumlah_disetujui ?: $det->jumlah),
                                        'terkirim' => 0,
                                        'sisa_picking' => (int) ($det->jumlah_disetujui ?: $det->jumlah),
                                        'stok_gudang' => 0,
                                    ];
                                @endphp
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2.5 px-3 text-center font-mono">{{ $idx + 1 }}</td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $det->barang?->nama ?? '-' }}</div>
                                        <div class="text-[11px] font-mono text-zinc-400">{{ $det->barang?->kode ?? '-' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-zinc-600 dark:text-zinc-400">{{ $det->barang?->satuan?->simbol ?? $det->barang?->satuan?->nama ?? 'Unit' }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono">{{ number_format($info['diminta'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-medium">{{ number_format($info['disetujui'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono text-zinc-500">{{ number_format($info['terkirim'], 0, ',', '.') }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold {{ $info['sisa_picking'] > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-400' }}">
                                        {{ number_format($info['sisa_picking'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono">
                                        @if ($info['stok_gudang'] <= 0)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                                0 (Kosong)
                                            </span>
                                        @elseif ($info['stok_gudang'] < $info['sisa_picking'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                {{ number_format($info['stok_gudang'], 0, ',', '.') }} (Kurang)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                {{ number_format($info['stok_gudang'], 0, ',', '.') }} (Siap)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-zinc-500 text-[11px]">{{ $det->catatan ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Footer Modal: Tombol Aksi --}}
                <div class="flex items-center justify-between pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" wire:click="closePermintaanDetailModal">
                        Tutup
                    </flux:button>
                    @canany(['create_gudang_stok::picking::distribusi'])
                        <flux:button
                            type="button"
                            variant="primary"
                            icon="cube"
                            wire:click="confirmPickingFromDetail"
                            class="bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold"
                        >
                            {{ $selectedPermintaanDetail->status === StatusPermintaanDistribusi::DikirimSebagian->value ? 'Lanjutkan Picking Sisa Hutang' : 'Lanjutkan Eksekusi Picking FEFO' }}
                        </flux:button>
                    @endcanany
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Konfirmasi Aksi --}}
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
