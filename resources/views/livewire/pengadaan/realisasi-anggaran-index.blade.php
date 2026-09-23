<div class="space-y-6">
    {{-- Header & Filter Tahun --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Pagu & Realisasi Beban Logistik Ruangan & IPSRS</flux:heading>
            <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Kalkulasi real-time berbasis pengeluaran fisik: distribusi amprah per unit dan konsumsi suku cadang pemeliharaan aset.
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">Tahun Anggaran:</span>
            <select
                wire:model.live="filterTahun"
                class="h-9 text-xs font-semibold rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
                <option value="2026">TA 2026 (Tahun Berjalan)</option>
                <option value="2025">TA 2025 (Arsip)</option>
            </select>
        </div>
    </div>

    {{-- 4 Kartu Ringkasan Finansial Eksekutif (Berbasis Pengeluaran Riil) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Pagu Global RS --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Pagu Operasional</span>
                <flux:icon name="banknotes" class="size-4 text-blue-600" />
            </div>
            <div class="text-xl font-bold font-mono text-zinc-900 dark:text-zinc-100">
                Rp {{ number_format($ringkasan['total_pagu'], 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-zinc-500">Plafon anggaran seluruh unit & IPSRS</div>
        </flux:card>

        {{-- Beban Distribusi Logistik Ruangan --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Pengeluaran Amprah Unit</span>
                <flux:icon name="truck" class="size-4 text-emerald-600" />
            </div>
            <div class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                Rp {{ number_format($ringkasan['total_realisasi_distribusi'], 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-zinc-500">Nilai komoditas keluar ke ruangan</div>
        </flux:card>

        {{-- Beban Suku Cadang Pemeliharaan IPSRS --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Konsumsi Sparepart IPSRS</span>
                <flux:icon name="wrench" class="size-4 text-indigo-600" />
            </div>
            <div class="text-xl font-bold font-mono text-indigo-600 dark:text-indigo-400">
                Rp {{ number_format($ringkasan['total_realisasi_sparepart'], 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-zinc-500">Suku cadang terpasang pada servis aset</div>
        </flux:card>

        {{-- Total Beban Riil & Serapan Global --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Beban Terpakai</span>
                <span class="text-xs font-bold font-mono {{ $ringkasan['serapan_global_persen'] > 75 ? 'text-amber-600' : 'text-blue-600' }}">
                    {{ $ringkasan['serapan_global_persen'] }}%
                </span>
            </div>
            <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden">
                <div
                    class="h-2 rounded-full transition-all duration-300 {{ $ringkasan['serapan_global_persen'] > 90 ? 'bg-rose-500' : ($ringkasan['serapan_global_persen'] > 75 ? 'bg-amber-500' : 'bg-blue-600') }}"
                    style="width: {{ min(100, $ringkasan['serapan_global_persen']) }}%"
                ></div>
            </div>
            <div class="text-[11px] text-zinc-500 flex justify-between">
                <span>Beban: Rp {{ number_format($ringkasan['total_realisasi_global'], 0, ',', '.') }}</span>
                <span>Sisa: Rp {{ number_format($ringkasan['total_sisa_global'], 0, ',', '.') }}</span>
            </div>
        </flux:card>
    </div>

    {{-- Tab Navigasi Sudut Pandang --}}
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-6">
        <button
            type="button"
            wire:click="$set('activeTab', 'unit')"
            class="pb-3 text-xs font-semibold transition-colors border-b-2 flex items-center gap-2 {{ $activeTab === 'unit' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
        >
            <flux:icon name="building-office-2" class="size-4" />
            <span>Pagu & Beban per Unit Ruangan (Cost Center)</span>
        </button>

        <button
            type="button"
            wire:click="$set('activeTab', 'sparepart')"
            class="pb-3 text-xs font-semibold transition-colors border-b-2 flex items-center gap-2 {{ $activeTab === 'sparepart' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
        >
            <flux:icon name="wrench-screwdriver" class="size-4" />
            <span>Plafon Suku Cadang & Pemeliharaan IPSRS</span>
        </button>
    </div>

    {{-- TAB 1: Pagu & Beban per Unit Ruangan --}}
    @if ($activeTab === 'unit')
        <flux:card class="space-y-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <flux:heading size="lg">Matriks Pembebanan Anggaran Ruangan</flux:heading>
                    <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400">
                        Beban dihitung otomatis dari barang yang diterima unit (amprah) dan sparepart servis aset di unit tersebut.
                    </flux:subheading>
                </div>
                <div class="w-full sm:w-64">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari ruangan atau kode pagu..."
                        class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="py-2.5 px-3 font-semibold">Kode Pagu</th>
                            <th class="py-2.5 px-3 font-semibold">Unit Kerja / Ruangan</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Pagu Alokasi (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Beban Amprah (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Beban Sparepart (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Total Beban (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Sisa Pagu (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-center">Serapan</th>
                            <th class="py-2.5 px-3 font-semibold text-center">Status</th>
                            <th class="py-2.5 px-3 font-semibold text-center">Rincian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($unitAnggarans as $anggaran)
                            @php
                                $pagu = (float) $anggaran->pagu;
                                $distribusi = (float) $anggaran->beban_distribusi;
                                $sparepart = (float) $anggaran->beban_sparepart;
                                $totalBeban = (float) $anggaran->realisasi;
                                $sisa = (float) $anggaran->sisa;
                                $persen = (float) $anggaran->serapan;
                                $statusColor = match($anggaran->status) {
                                    'Normal' => 'emerald',
                                    'Waspada' => 'amber',
                                    'Over-Budget' => 'rose',
                                    default => 'zinc',
                                };
                            @endphp
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30" wire:key="unit-pagu-{{ $anggaran->id }}">
                                <td class="py-2.5 px-3 font-mono font-semibold text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                                    {{ $anggaran->kode }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $anggaran->unit?->nama ?? $anggaran->pos_anggaran }}
                                    </div>
                                    <div class="text-[10px] text-zinc-400 font-mono">Kode Unit: {{ $anggaran->unit?->kode ?? '-' }}</div>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right text-zinc-700 dark:text-zinc-300 tabular-nums">
                                    Rp {{ number_format($pagu, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right text-emerald-600 dark:text-emerald-400 font-medium tabular-nums">
                                    Rp {{ number_format($distribusi, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right text-indigo-600 dark:text-indigo-400 font-medium tabular-nums">
                                    Rp {{ number_format($sparepart, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right font-bold text-zinc-900 dark:text-zinc-100 tabular-nums">
                                    Rp {{ number_format($totalBeban, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right font-semibold text-zinc-800 dark:text-zinc-200 tabular-nums">
                                    Rp {{ number_format($sisa, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <div class="w-12 bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 overflow-hidden">
                                            <div class="h-1.5 rounded-full {{ $persen > 75 ? 'bg-amber-500' : 'bg-blue-600' }}" style="width: {{ min(100, $persen) }}%"></div>
                                        </div>
                                        <span class="font-mono text-[11px] font-semibold text-zinc-700 dark:text-zinc-300">{{ $persen }}%</span>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <flux:badge size="sm" :color="$statusColor">{{ $anggaran->status }}</flux:badge>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <flux:button
                                        wire:click="openDetail({{ $anggaran->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="eye"
                                        title="Buka Drilldown Bukti Pengeluaran"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-zinc-500 text-xs">
                                    Belum ada data alokasi pagu ruangan yang terdaftar untuk TA {{ $filterTahun }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    {{-- TAB 2: Pos Suku Cadang & Pemeliharaan IPSRS --}}
    @if ($activeTab === 'sparepart')
        <div class="space-y-6">
            {{-- Banner Plafon Sparepart IPSRS --}}
            @if ($sparepartAnggaran)
                @php
                    $paguSp = (float) $sparepartAnggaran->pagu;
                    $realisasiSp = (float) $sparepartAnggaran->realisasi;
                    $sisaSp = (float) $sparepartAnggaran->sisa;
                    $serapanSp = (float) $sparepartAnggaran->serapan;
                @endphp
                <flux:card class="p-5 border-l-4 border-l-indigo-600 bg-indigo-50/30 dark:bg-indigo-950/20 shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-mono font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                                {{ $sparepartAnggaran->kode }} &bull; TA {{ $filterTahun }}
                            </div>
                            <flux:heading size="lg" class="mt-0.5">{{ $sparepartAnggaran->pos_anggaran }}</flux:heading>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                Kuota biaya sparepart rumah sakit yang dipotong otomatis setiap teknisi IPSRS mencatat pergantian suku cadang pada lembar kerja SPK.
                            </p>
                        </div>
                        <div class="text-right sm:border-l sm:border-zinc-200 dark:sm:border-zinc-700 sm:pl-6">
                            <span class="text-xs text-zinc-500">Persentase Serapan Kuota</span>
                            <div class="text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400">{{ $serapanSp }}%</div>
                            <flux:badge size="sm" :color="$sparepartAnggaran->status === 'Normal' ? 'emerald' : 'amber'">{{ $sparepartAnggaran->status }}</flux:badge>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 mt-4 border-t border-zinc-200/80 dark:border-zinc-700/80 text-xs">
                        <div>
                            <span class="text-zinc-500">Plafon Anggaran:</span>
                            <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">
                                Rp {{ number_format($paguSp, 0, ',', '.') }}
                            </div>
                        </div>
                        <div>
                            <span class="text-zinc-500">Total Sparepart Terpakai:</span>
                            <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-sm mt-0.5">
                                Rp {{ number_format($realisasiSp, 0, ',', '.') }}
                            </div>
                        </div>
                        <div>
                            <span class="text-zinc-500">Sisa Kuota Anggaran:</span>
                            <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5">
                                Rp {{ number_format($sisaSp, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endif

            {{-- 2 Grid: Top Aset Penyerap Biaya & Riwayat Log Sparepart --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                {{-- Panel Kiri: Top 5 Aset Penyerap Biaya Pemeliharaan --}}
                <flux:card class="space-y-3 shadow-sm border-t-2 border-t-indigo-500">
                    <div>
                        <flux:heading size="base" class="font-bold">Top Aset Penyerap Biaya Pemeliharaan</flux:heading>
                        <flux:subheading class="text-xs text-zinc-500">
                            Aset sarana rumah sakit dengan konsumsi suku cadang dan biaya servis tertinggi.
                        </flux:subheading>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
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
                                            <div class="text-[10px] text-zinc-400 font-mono">{{ $aset->kode }}</div>
                                        </td>
                                        <td class="py-2.5 px-3 text-zinc-600 dark:text-zinc-400">
                                            {{ $aset->unit?->nama ?? '-' }}
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
                                        <td colspan="4" class="py-6 text-center text-zinc-500 text-xs">
                                            Belum ada data pemakaian suku cadang pada aset untuk TA {{ $filterTahun }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>

                {{-- Panel Kanan: Riwayat 10 Log Pemakaian Suku Cadang Terakhir --}}
                <flux:card class="space-y-3 shadow-sm border-t-2 border-t-emerald-500">
                    <div>
                        <flux:heading size="base" class="font-bold">Log Konsumsi Suku Cadang Terakhir</flux:heading>
                        <flux:subheading class="text-xs text-zinc-500">
                            Catatan suku cadang yang dipasang teknisi pada tiket servis SPK.
                        </flux:subheading>
                    </div>

                    <div class="overflow-x-auto max-h-80">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
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
                                            <div class="text-[10px] text-zinc-400">
                                                {{ $spLog->permintaanMaintenance?->aset?->nama ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-3">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $spLog->barang?->nama ?? '-' }}
                                            </div>
                                            <div class="text-[10px] text-zinc-400 font-mono">
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
                                        <td colspan="4" class="py-6 text-center text-zinc-500 text-xs">
                                            Belum ada log pergantian suku cadang yang tercatat.
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
        @if ($selectedAnggaran)
            @php
                $paguMod = (float) $selectedAnggaran->pagu;
                $totMod = (float) $selectedAnggaran->realisasi;
                $sisaMod = (float) $selectedAnggaran->sisa;
                $persenMod = (float) $selectedAnggaran->serapan;
            @endphp
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="text-[11px] font-mono text-blue-600 dark:text-blue-400 font-bold uppercase">
                            {{ $selectedAnggaran->kode }} &bull; TA {{ $selectedAnggaran->tahun }}
                        </div>
                        <flux:heading size="lg" class="mt-0.5">
                            {{ $selectedAnggaran->unit?->nama ?? $selectedAnggaran->pos_anggaran }}
                        </flux:heading>
                    </div>
                    <flux:badge size="sm" :color="$selectedAnggaran->status === 'Normal' ? 'emerald' : ($selectedAnggaran->status === 'Waspada' ? 'amber' : 'rose')">
                        {{ $selectedAnggaran->status }}
                    </flux:badge>
                </div>

                {{-- Ringkasan 3 Stat Box Modal --}}
                <div class="grid grid-cols-3 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Pagu Alokasi Unit:</span>
                        <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            Rp {{ number_format($paguMod, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Total Beban Realisasi:</span>
                        <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">
                            Rp {{ number_format($totMod, 0, ',', '.') }} ({{ $persenMod }}%)
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Sisa Pagu Tersedia:</span>
                        <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            Rp {{ number_format($sisaMod, 0, ',', '.') }}
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
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
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
                                        <td class="py-2 px-3 font-mono text-right text-emerald-600 font-semibold tabular-nums">
                                            Rp {{ number_format($nilaiSppb, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-zinc-500 text-xs">
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
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
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
                                        <td class="py-2 px-3 font-mono text-right text-indigo-600 font-semibold tabular-nums">
                                            Rp {{ number_format($servis->subtotal ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-zinc-500 text-xs">
                                            Belum ada suku cadang pemeliharaan yang dibebankan pada aset ruangan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="primary" size="sm" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
