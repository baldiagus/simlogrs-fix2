<div class="space-y-6">
    {{-- Header Dashboard --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Dashboard Kepala Gudang Logistik</flux:heading>
            <flux:subheading>Pengawasan integritas batch FEFO, otorisasi opname fisik, dan tata letak penyimpanan.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="amber" inset="top bottom">Operasional Gudang</flux:badge>
            <x-ui.status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Grid 4 KPI Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Kedaluwarsa Kritis</span>
                <flux:icon name="exclamation-triangle" class="size-4 text-rose-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-rose-600 dark:text-rose-400 tabular-nums">
                {{ $totalBatchKritis }}
            </div>
            <div class="text-[11px] text-zinc-500">Batch kedaluwarsa &le; 90 hari</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Opname Berjalan</span>
                <flux:icon name="clipboard-document-check" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 tabular-nums">
                {{ $totalOpnameBerjalan }}
            </div>
            <div class="text-[11px] text-zinc-500">Sesi hitung fisik sedang aktif</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Otorisasi Selisih</span>
                <flux:icon name="adjustments-horizontal" class="size-4 text-amber-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 tabular-nums">
                {{ $totalPenyesuaianMenunggu }}
            </div>
            <div class="text-[11px] text-zinc-500">Varians opname menunggu persetujuan</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Batch Aktif</span>
                <flux:icon name="cube" class="size-4 text-emerald-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalBatchAktif }}
            </div>
            <div class="text-[11px] text-zinc-500">Batch fisik dalam fasilitas simpan</div>
        </flux:card>
    </div>

    {{-- 2 Kolom Panel Operasional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Panel Kiri: Varians Selisih Menunggu Approval --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="scale" class="size-5 text-amber-500" />
                    <flux:heading size="md">Otorisasi Penyesuaian Selisih</flux:heading>
                </div>
                <a href="/kepala-gudang/otorisasi-penyesuaian" class="text-xs font-semibold text-blue-600 hover:underline">
                    Buka Semua &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($penyesuaianMenunggu as $item)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $item->kode ?? ('ADJ-' . $item->id) }}</div>
                            <div class="text-[11px] text-zinc-500">
                                {{ $item->gudang?->nama ?? 'Gudang' }} &bull; Pengaju: {{ $item->pengaju?->nama ?? '-' }}
                            </div>
                            <div class="text-[10px] text-zinc-400 mt-0.5">{{ Str::limit($item->alasan ?? 'Penyesuaian hasil opname', 45) }}</div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="amber">Menunggu Approval</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $item->dibuat_pada?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada usulan penyesuaian selisih yang menunggu otorisasi.
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Panel Kanan: Batch FEFO Kritis --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="clock" class="size-5 text-rose-500" />
                    <flux:heading size="md">Early Warning FEFO (&le; 90 Hari)</flux:heading>
                </div>
                <a href="/kepala-gudang/monitoring-fefo" class="text-xs font-semibold text-blue-600 hover:underline">
                    Buka FEFO &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($batchKritis as $batch)
                    @php
                        $sisaHari = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($batch->tanggal_kedaluwarsa)->startOfDay(), false);
                    @endphp
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $batch->barang?->nama ?? 'Barang' }}</div>
                            <div class="text-[11px] text-zinc-500 font-mono">
                                Batch: {{ $batch->nomor_batch }} &bull; Sisa: {{ number_format($batch->jumlah) }} {{ $batch->barang?->satuan?->nama ?? 'Unit' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="{{ $sisaHari <= 30 ? 'rose' : 'amber' }}">
                                {{ $sisaHari <= 0 ? 'Kedaluwarsa' : $sisaHari . ' hari lagi' }}
                            </flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">Exp: {{ \Carbon\Carbon::parse($batch->tanggal_kedaluwarsa)->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Seluruh batch logistik berada dalam rentang aman.
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Akses Cepat Operasional --}}
    <flux:card class="space-y-4 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <flux:heading size="md">Pintasan Otoritas Kepala Gudang</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <a href="/kepala-gudang/stock-opname" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="clipboard-document-check" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Stock Opname</div>
                    <div class="text-[10px] text-zinc-500">Inisiasi siklus hitung fisik</div>
                </div>
            </a>
            <a href="/kepala-gudang/otorisasi-penyesuaian" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="adjustments-horizontal" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Otorisasi Selisih</div>
                    <div class="text-[10px] text-zinc-500">Persetujuan varians stok</div>
                </div>
            </a>
            <a href="/kepala-gudang/monitoring-fefo" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="clock" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Monitoring FEFO</div>
                    <div class="text-[10px] text-zinc-500">Pengawasan masa simpan</div>
                </div>
            </a>
            <a href="/kepala-gudang/tata-letak-rak" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="squares-2x2" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Tata Letak Rak</div>
                    <div class="text-[10px] text-zinc-500">Visualisasi denah & rak</div>
                </div>
            </a>
        </div>
    </flux:card>
</div>
