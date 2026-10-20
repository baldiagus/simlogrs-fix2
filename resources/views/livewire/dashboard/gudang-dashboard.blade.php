<div class="space-y-6">
    {{-- Header Dashboard --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Dashboard Petugas Gudang</flux:heading>
            <flux:subheading>Pelaksanaan fisik inbound barang vendor, picking FEFO, dan penataan rak simpan.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="emerald" inset="top bottom">Operasional Lapangan</flux:badge>
            <x-ui.status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Grid 4 KPI Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Antrean Picking</span>
                <flux:icon name="hand-raised" class="size-4 text-emerald-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                {{ $totalAntreanPicking }}
            </div>
            <div class="text-[11px] text-zinc-500">SPPB siap proses packing FEFO</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Inbound PO Vendor</span>
                <flux:icon name="arrow-down-tray" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 tabular-nums">
                {{ $totalInboundPo }}
            </div>
            <div class="text-[11px] text-zinc-500">PO kirim siap cek fisik & terima</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">SPPB Dalam Kirim</span>
                <flux:icon name="truck" class="size-4 text-amber-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalSppbDikirim }}
            </div>
            <div class="text-[11px] text-zinc-500">Paket bergerak menuju ruangan</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Batch di Rak</span>
                <flux:icon name="cube" class="size-4 text-zinc-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalBatchAktif }}
            </div>
            <div class="text-[11px] text-zinc-500">Total batch fisik tersimpan</div>
        </flux:card>
    </div>

    {{-- 2 Kolom Panel Operasional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Panel Kiri: Antrean Picking FEFO --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="queue-list" class="size-5 text-emerald-500" />
                    <flux:heading size="md">Antrean Picking Distribusi (SPPB)</flux:heading>
                </div>
                <a href="/petugas-gudang/picking-distribusi" class="text-xs font-semibold text-blue-600 hover:underline">
                    Buka Picking &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($antreanPicking as $item)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $item->kode }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Tujuan: {{ $item->unit?->nama ?? 'Unit Umum' }} &bull; Pengaju: {{ $item->pengaju?->nama ?? '-' }}
                            </div>
                            <div class="text-[10px] text-zinc-400 mt-0.5">{{ $item->detail->count() }} komoditas disetujui</div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="emerald">Siap Picking</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $item->dibuat_pada?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada antrean picking distribusi saat ini.
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Panel Kanan: Inbound PO Vendor Menunggu Diterima --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="inbox-arrow-down" class="size-5 text-blue-600" />
                    <flux:heading size="md">Kedatangan Inbound PO Vendor</flux:heading>
                </div>
                <a href="/petugas-gudang/penerimaan-po" class="text-xs font-semibold text-blue-600 hover:underline">
                    Penerimaan PO &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($inboundPo as $po)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $po->kode }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Rekanan: {{ $po->pemasok?->nama ?? 'Pemasok Rekanan' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="blue">{{ str_replace('_', ' ', $po->status) }}</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $po->tanggal_po?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada jadwal kedatangan pengiriman PO vendor yang tertunda.
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Akses Cepat Operasional --}}
    <flux:card class="space-y-4 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <flux:heading size="md">Pintasan Kerja Fisik Gudang</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <a href="/petugas-gudang/picking-distribusi" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="hand-raised" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Picking FEFO</div>
                    <div class="text-[10px] text-zinc-500">Pengambilan barang di rak</div>
                </div>
            </a>
            <a href="/petugas-gudang/penerimaan-po" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="arrow-down-tray" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Penerimaan PO</div>
                    <div class="text-[10px] text-zinc-500">Inbound cek fisik vendor</div>
                </div>
            </a>
            <a href="/petugas-gudang/stock-opname" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="calculator" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Hitung Opname Fisik</div>
                    <div class="text-[10px] text-zinc-500">Input lembar blind count</div>
                </div>
            </a>
            <a href="/petugas-gudang/tata-letak-rak" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="squares-2x2" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Tata Letak Rak</div>
                    <div class="text-[10px] text-zinc-500">Peta bin & relokasi barang</div>
                </div>
            </a>
        </div>
    </flux:card>
</div>
