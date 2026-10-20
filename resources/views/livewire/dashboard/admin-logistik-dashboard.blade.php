<div class="space-y-6">
    {{-- Header Dashboard --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Dashboard Admin Logistik Sentral</flux:heading>
            <flux:subheading>Pengawasan distribusi stok sentral, kuota barang, dan sirkulasi gudang logistik.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="sky" inset="top bottom">Logistik Sentral</flux:badge>
            <x-ui.status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Grid 4 KPI Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Antrean Otorisasi</span>
                <flux:icon name="clipboard-document-check" class="size-4 text-amber-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalAntreanOtorisasi }}
            </div>
            <div class="text-[11px] text-zinc-500">Amprah menunggu persetujuan Meja 2</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">SPPB Dalam Kirim</span>
                <flux:icon name="truck" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 tabular-nums">
                {{ $totalSppbDikirim }}
            </div>
            <div class="text-[11px] text-zinc-500">Pengiriman menuju unit ruangan</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Batch Kritis FEFO</span>
                <flux:icon name="exclamation-triangle" class="size-4 text-rose-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-rose-600 dark:text-rose-400 tabular-nums">
                {{ $totalBatchKritis }}
            </div>
            <div class="text-[11px] text-zinc-500">Kedaluwarsa dalam &le; 90 hari</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Pengalihan Aktif</span>
                <flux:icon name="arrows-right-left" class="size-4 text-indigo-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalPengalihanAktif }}
            </div>
            <div class="text-[11px] text-zinc-500">Mutasi fisik antar gudang aktif</div>
        </flux:card>
    </div>

    {{-- 2 Kolom Panel Operasional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Panel Kiri: Antrean Otorisasi Distribusi --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="queue-list" class="size-5 text-amber-500" />
                    <flux:heading size="md">Antrean Otorisasi Distribusi</flux:heading>
                </div>
                <a href="/admin-logistik/distribusi-approval" class="text-xs font-semibold text-blue-600 hover:underline">
                    Buka Semua &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($antreanOtorisasi as $item)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $item->kode }}</div>
                            <div class="text-[11px] text-zinc-500">
                                {{ $item->unit?->nama ?? 'Unit Umum' }} &bull; Pengaju: {{ $item->pengaju?->nama ?? '-' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="amber">Disetujui Karu</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $item->dibuat_pada?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada antrean otorisasi distribusi yang tertunda.
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Panel Kanan: Batch Stok Kritis FEFO --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="clock" class="size-5 text-rose-500" />
                    <flux:heading size="md">Peringatan Kedaluwarsa Kritis (&le; 90 Hari)</flux:heading>
                </div>
                <a href="/admin-logistik/monitoring-fefo" class="text-xs font-semibold text-blue-600 hover:underline">
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
        <flux:heading size="md">Pintasan Operasional Logistik Sentral</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <a href="/admin-logistik/distribusi-approval" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="clipboard-document-check" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Otorisasi Meja 2</div>
                    <div class="text-[10px] text-zinc-500">Review alokasi amprah</div>
                </div>
            </a>
            <a href="/admin-logistik/pengalihan-barang" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="arrows-right-left" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Pengalihan Barang</div>
                    <div class="text-[10px] text-zinc-500">Mutasi stok antar gudang</div>
                </div>
            </a>
            <a href="/admin-logistik/monitoring-fefo" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="shield-check" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Monitoring FEFO</div>
                    <div class="text-[10px] text-zinc-500">Kendali masa simpan fisik</div>
                </div>
            </a>
            <a href="/admin-logistik/buku-mutasi" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="book-open" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Buku Mutasi Stok</div>
                    <div class="text-[10px] text-zinc-500">Histori kartu stok fisik</div>
                </div>
            </a>
        </div>
    </flux:card>
</div>
