<div class="space-y-6">
    {{-- Header & Selector Tahun Anggaran --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Screening Pengeluaran Logistik Ruangan & Suku Cadang IPSRS</flux:heading>
            <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                Monitoring pengeluaran fisik riil: distribusi amprah per unit dan konsumsi suku cadang pemeliharaan aset.
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <label for="filter-tahun" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Tahun Anggaran:</label>
            <select
                id="filter-tahun"
                wire:model.live="filterTahun"
                class="h-9 text-xs font-semibold rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
                <option value="2026">TA 2026 (Tahun Berjalan)</option>
                <option value="2025">TA 2025 (Arsip)</option>
            </select>
        </div>
    </div>

    {{-- Navigasi Sub-Tab Hub Evaluasi --}}
    <x-pengadaan-evaluasi-tabs active="anggaran" />

    {{-- 2 Kartu Screening Pengeluaran Riil (Tanpa Stripe Dekoratif - Anti Slop R-01 & R-31) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {{-- Pengeluaran Amprah Unit --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Pengeluaran Amprah Unit</span>
                <flux:icon name="truck" class="size-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                Rp {{ number_format($ringkasan['total_realisasi_distribusi'], 0, ',', '.') }}
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Akumulasi nilai komoditas logistik yang telah didistribusikan dari gudang ke unit ruangan pada TA {{ $filterTahun }}.
            </p>
        </flux:card>

        {{-- Konsumsi Sparepart IPSRS --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Konsumsi Sparepart IPSRS</span>
                <flux:icon name="wrench" class="size-5 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div class="text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400 tabular-nums">
                Rp {{ number_format($ringkasan['total_realisasi_sparepart'], 0, ',', '.') }}
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Akumulasi nilai suku cadang terpasang pada servis perbaikan sarana rumah sakit pada TA {{ $filterTahun }}.
            </p>
        </flux:card>
    </div>

    {{-- Tab Navigasi Sudut Pandang --}}
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-6">
        <button
            type="button"
            wire:click="$set('activeTab', 'unit')"
            class="pb-3 text-xs font-semibold transition-colors border-b-2 flex items-center gap-2 {{ $activeTab === 'unit' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
        >
            <flux:icon name="building-office-2" class="size-4" />
            <span>Screening Pengeluaran Tiap Unit Ruangan</span>
        </button>

        <button
            type="button"
            wire:click="$set('activeTab', 'sparepart')"
            class="pb-3 text-xs font-semibold transition-colors border-b-2 flex items-center gap-2 {{ $activeTab === 'sparepart' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
        >
            <flux:icon name="wrench-screwdriver" class="size-4" />
            <span>Konsumsi Suku Cadang & Pemeliharaan IPSRS</span>
        </button>
    </div>

    {{-- TAB 1: Screening Pengeluaran Tiap Unit Ruangan --}}
    @if ($activeTab === 'unit')
        <flux:card class="space-y-4 shadow-sm border border-zinc-200 dark:border-zinc-800">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <flux:heading size="lg">Screening Pengeluaran Tiap Unit Ruangan</flux:heading>
                    <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400">
                        Screening nilai barang logistik yang diterima unit (amprah) dan suku cadang servis sarana di unit tersebut.
                    </flux:subheading>
                </div>
                <div class="w-full sm:w-64">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari unit ruangan..."
                        class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="py-2.5 px-3 font-semibold">Kode</th>
                            <th class="py-2.5 px-3 font-semibold">Unit Kerja / Ruangan</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Pengeluaran Amprah (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Konsumsi Sparepart (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Total Pengeluaran Unit (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-center">Rincian Drilldown</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($unitAnggarans as $anggaran)
                            @php
                                $distribusi = (float) $anggaran->beban_distribusi;
                                $sparepart = (float) $anggaran->beban_sparepart;
                                $totalBeban = (float) $anggaran->realisasi;
                            @endphp
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40" wire:key="unit-row-{{ $anggaran->id }}">
                                <td class="py-2.5 px-3 font-mono font-semibold text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                                    {{ $anggaran->kode }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $anggaran->nama ?? $anggaran->unit?->nama ?? $anggaran->pos_anggaran }}
                                    </div>
                                    <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">Kode: {{ $anggaran->kode }}</div>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right text-emerald-600 dark:text-emerald-400 font-semibold tabular-nums">
                                    Rp {{ number_format($distribusi, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right text-indigo-600 dark:text-indigo-400 font-semibold tabular-nums">
                                    Rp {{ number_format($sparepart, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right font-bold text-zinc-900 dark:text-zinc-100 tabular-nums">
                                    Rp {{ number_format($totalBeban, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <flux:button
                                        wire:click="openDetail({{ $anggaran->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="eye"
                                        title="Buka Rincian Bukti Fisik"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                    Tidak ada data unit kerja yang sesuai dengan kata kunci pencarian pada TA {{ $filterTahun }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    {{-- TAB 2: Screening Suku Cadang & Pemeliharaan IPSRS --}}
    @if ($activeTab === 'sparepart')
        <div class="space-y-6">
            {{-- Banner Total Suku Cadang IPSRS --}}
            @if (isset($ringkasan['total_realisasi_sparepart']))
                @php
                    $realisasiSp = (float) $ringkasan['total_realisasi_sparepart'];
                @endphp
                <flux:card class="p-5 border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/40 shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-mono font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                                Screening Pemeliharaan Sarana, TA {{ $filterTahun }}
                            </div>
                            <flux:heading size="lg" class="mt-0.5">Total Pengeluaran Suku Cadang IPSRS</flux:heading>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                Rekapitulasi biaya suku cadang yang dipasang teknisi IPSRS pada lembar kerja SPK perbaikan sarana rumah sakit.
                            </p>
                        </div>
                        <div class="text-right sm:border-l sm:border-zinc-200 dark:sm:border-zinc-700 sm:pl-6">
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">Total Biaya Suku Cadang Terpakai</span>
                            <div class="text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400 tabular-nums">
                                Rp {{ number_format($realisasiSp, 0, ',', '.') }}
                            </div>
                            <span class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">Status: Akumulasi Real-Time</span>
                        </div>
                    </div>
                </flux:card>
            @endif

            {{-- 2 Grid: Top Aset Penyerap Biaya & Riwayat Log Sparepart --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                {{-- Panel Kiri: Top 5 Aset Penyerap Biaya Pemeliharaan --}}
                <flux:card class="space-y-3 shadow-sm border border-zinc-200 dark:border-zinc-800">
                    <div>
                        <flux:heading size="base" class="font-bold">Top Aset Penyerap Biaya Pemeliharaan</flux:heading>
                        <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400">
                            Aset sarana rumah sakit dengan konsumsi suku cadang dan biaya servis tertinggi.
                        </flux:subheading>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2 px-3 font-semibold">Nama Sarana Aset</th>
                                    <th class="py-2 px-3 font-semibold">Lokasi Ruangan</th>
                                    <th class="py-2 px-3 font-semibold text-center">Servis</th>
                                    <th class="py-2 px-3 font-semibold text-right">Biaya Sparepart (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($topAset as $aset)
                                    <tr>
                                        <td class="py-2.5 px-3">
                                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $aset->nama }}</div>
                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $aset->kode_aset ?? $aset->kode }}</div>
                                        </td>
                                        <td class="py-2.5 px-3 text-zinc-600 dark:text-zinc-400">
                                            {{ $aset->unit?->nama ?? 'Sentral' }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-mono font-medium">
                                            {{ $aset->frekuensi_servis }}x
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-right text-indigo-600 dark:text-indigo-400 font-bold tabular-nums">
                                            Rp {{ number_format($aset->total_biaya_sparepart ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada data pemakaian suku cadang pada aset untuk TA {{ $filterTahun }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>

                {{-- Panel Kanan: Riwayat 10 Log Pemakaian Suku Cadang Terakhir --}}
                <flux:card class="space-y-3 shadow-sm border border-zinc-200 dark:border-zinc-800">
                    <div>
                        <flux:heading size="base" class="font-bold">Log Konsumsi Suku Cadang Terakhir</flux:heading>
                        <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400">
                            Catatan suku cadang yang dipasang teknisi pada tiket servis SPK.
                        </flux:subheading>
                    </div>

                    <div class="overflow-x-auto max-h-80">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                                <tr>
                                    <th class="py-2 px-3 font-semibold">Tiket SPK</th>
                                    <th class="py-2 px-3 font-semibold">Nama Sparepart</th>
                                    <th class="py-2 px-3 font-semibold text-center">Jumlah</th>
                                    <th class="py-2 px-3 font-semibold text-right">Subtotal (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($riwayatSparepartTerbaru as $spLog)
                                    <tr>
                                        <td class="py-2 px-3">
                                            <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ $spLog->permintaanMaintenance?->kode ?? '-' }}
                                            </div>
                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">
                                                {{ $spLog->permintaanMaintenance?->aset?->nama ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-3">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $spLog->barang?->nama ?? '-' }}
                                            </div>
                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">
                                                Batch: {{ $spLog->stokBatch?->nomor_batch ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-3 text-center font-mono font-medium">
                                            {{ $spLog->jumlah_digunakan }}
                                        </td>
                                        <td class="py-2 px-3 font-mono text-right text-emerald-600 dark:text-emerald-400 font-semibold tabular-nums">
                                            Rp {{ number_format($spLog->subtotal ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada log pergantian suku cadang yang tercatat untuk TA {{ $filterTahun }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        </div>
    @endif

    {{-- Modal Drilldown Pengeluaran Unit (SPPB Distribusi & Pemeliharaan Sparepart) --}}
    <flux:modal name="modal-drilldown-unit" wire:model="showDetailModal" class="md:w-[48rem]">
        @if ($selectedUnit || $selectedAnggaran)
            @php
                $activeUnit = $selectedUnit ?? $selectedAnggaran;
                $totMod = (float) ($selectedBebanDistribusi + $selectedBebanSparepart);
            @endphp
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="text-[11px] font-mono text-blue-600 dark:text-blue-400 font-bold uppercase">
                            Screening Pengeluaran, TA {{ $filterTahun }}
                        </div>
                        <flux:heading size="lg" class="mt-0.5">
                            {{ $activeUnit->nama ?? $activeUnit->unit?->nama ?? $activeUnit->pos_anggaran }}
                        </flux:heading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <flux:badge size="sm" color="blue">
                            Unit Kerja ({{ $activeUnit->kode }})
                        </flux:badge>
                    </div>
                </div>

                {{-- Ringkasan 3 Stat Box Modal --}}
                <div class="grid grid-cols-3 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 text-xs">
                    <div>
                        <span class="text-zinc-600 dark:text-zinc-400 text-[11px]">Pengeluaran Amprah:</span>
                        <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm mt-0.5 tabular-nums">
                            Rp {{ number_format($selectedBebanDistribusi, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-600 dark:text-zinc-400 text-[11px]">Konsumsi Sparepart:</span>
                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-sm mt-0.5 tabular-nums">
                            Rp {{ number_format($selectedBebanSparepart, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-600 dark:text-zinc-400 text-[11px]">Total Pengeluaran Unit:</span>
                        <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5 tabular-nums">
                            Rp {{ number_format($totMod, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                {{-- Bagian 1: Riwayat Pengiriman Distribusi Barang (SPPB) --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Bukti Surat Jalan Distribusi (SPPB) yang Diterima Ruangan</flux:heading>
                        <flux:badge size="xs" color="emerald">{{ $riwayatSppbUnit->count() }} Dokumen</flux:badge>
                    </div>

                    <div class="overflow-x-auto max-h-48 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2 px-3">Nomor SPPB</th>
                                    <th class="py-2 px-3">Tanggal Kirim</th>
                                    <th class="py-2 px-3">Asal Gudang</th>
                                    <th class="py-2 px-3">Daftar Komoditas</th>
                                    <th class="py-2 px-3 text-right">Nilai Barang</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($riwayatSppbUnit as $sppb)
                                    @php
                                        $nilaiSppb = $sppb->detail->sum(function ($d) {
                                            return $d->jumlah_dikirim * ($d->stokBatch?->harga_satuan ?? 0);
                                        });
                                    @endphp
                                    <tr>
                                        <td class="py-2 px-3 font-mono font-semibold">{{ $sppb->kode }}</td>
                                        <td class="py-2 px-3 font-mono text-zinc-500">{{ $sppb->tanggal_kirim ? $sppb->tanggal_kirim->format('d/m/Y') : '-' }}</td>
                                        <td class="py-2 px-3 text-zinc-700 dark:text-zinc-300">{{ $sppb->gudang?->nama ?? '-' }}</td>
                                        <td class="py-2 px-3 text-zinc-600 dark:text-zinc-400">
                                            @foreach ($sppb->detail as $itemDetail)
                                                <div class="truncate max-w-xs">&bull; {{ $itemDetail->barang?->nama }} ({{ $itemDetail->jumlah_dikirim }})</div>
                                            @endforeach
                                        </td>
                                        <td class="py-2 px-3 font-mono text-right text-emerald-600 dark:text-emerald-400 font-semibold tabular-nums">
                                            Rp {{ number_format($nilaiSppb, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada pengiriman distribusi barang yang dibebankan ke unit ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Bagian 2: Riwayat Konsumsi Sparepart Aset Ruangan --}}
                <div class="space-y-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Suku Cadang yang Dikonsumsi untuk Servis Aset Ruangan Ini</flux:heading>
                        <flux:badge size="xs" color="indigo">{{ $riwayatServisUnit->count() }} Komponen</flux:badge>
                    </div>

                    <div class="overflow-x-auto max-h-48 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2 px-3">Tiket Servis</th>
                                    <th class="py-2 px-3">Sarana / Aset</th>
                                    <th class="py-2 px-3">Nama Sparepart</th>
                                    <th class="py-2 px-3 text-center">Jumlah</th>
                                    <th class="py-2 px-3 text-right">Subtotal (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($riwayatServisUnit as $servis)
                                    <tr>
                                        <td class="py-2 px-3 font-mono font-semibold">{{ $servis->permintaanMaintenance?->kode ?? '-' }}</td>
                                        <td class="py-2 px-3 text-zinc-800 dark:text-zinc-200">{{ $servis->permintaanMaintenance?->aset?->nama ?? '-' }}</td>
                                        <td class="py-2 px-3 text-zinc-700 dark:text-zinc-300">{{ $servis->barang?->nama ?? '-' }}</td>
                                        <td class="py-2 px-3 text-center font-mono">{{ $servis->jumlah_digunakan }}</td>
                                        <td class="py-2 px-3 font-mono text-right text-indigo-600 dark:text-indigo-400 font-semibold tabular-nums">
                                            Rp {{ number_format($servis->subtotal ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada suku cadang pemeliharaan yang dibebankan pada aset ruangan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @endif
    </flux:modal>
</div>
