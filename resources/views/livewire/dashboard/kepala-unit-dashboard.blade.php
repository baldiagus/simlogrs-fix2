<div class="space-y-6">
    {{-- Header Dashboard --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Dashboard Kepala Unit / Ruangan</flux:heading>
            <flux:subheading>Otorisasi amprah rutin, permohonan pinjam aset, dan pengawasan fasilitas unit kerja.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="cyan" inset="top bottom">Kepala Ruangan</flux:badge>
            <x-ui.status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Grid 4 KPI Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Approval Amprah</span>
                <flux:icon name="inbox-arrow-down" class="size-4 text-cyan-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-cyan-600 dark:text-cyan-400 tabular-nums">
                {{ $totalAntreanAmprah }}
            </div>
            <div class="text-[11px] text-zinc-500">Amprah menunggu persetujuan Karu</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Izin Pinjam Aset</span>
                <flux:icon name="key" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 tabular-nums">
                {{ $totalAntreanPinjam }}
            </div>
            <div class="text-[11px] text-zinc-500">Permohonan pinjam alat ruangan</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Servis Fasilitas Aktif</span>
                <flux:icon name="wrench" class="size-4 text-amber-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalServisAktif }}
            </div>
            <div class="text-[11px] text-zinc-500">Tiket perbaikan alat dalam proses IPSRS</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Katalog Stok BHP</span>
                <flux:icon name="cube" class="size-4 text-zinc-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalStokRuangan }}
            </div>
            <div class="text-[11px] text-zinc-500">Jenis barang persediaan lokal unit</div>
        </flux:card>
    </div>

    {{-- 2 Kolom Panel Operasional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Panel Kiri: Antrean Approval Amprah Karu --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="clipboard-document-check" class="size-5 text-cyan-500" />
                    <flux:heading size="md">Otorisasi Permintaan Amprah (Meja 1)</flux:heading>
                </div>
                <a href="/kepala-unit/approval-permintaan" class="text-xs font-semibold text-blue-600 hover:underline">
                    Buka Approval &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($antreanAmprah as $item)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $item->kode }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Pengaju: {{ $item->pengaju?->nama ?? '-' }} &bull; Tujuan Gudang: {{ $item->gudang?->nama ?? 'Sentral' }}
                            </div>
                            <div class="text-[10px] text-zinc-400 mt-0.5">{{ $item->detail->count() }} komoditas barang diajukan</div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="cyan">Menunggu Karu</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $item->dibuat_pada?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada antrean amprah barang yang menunggu persetujuan Karu.
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Panel Kanan: Permohonan Pinjam Aset Ruangan --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="arrows-right-left" class="size-5 text-blue-600" />
                    <flux:heading size="md">Permohonan Pinjam Alat Ruangan</flux:heading>
                </div>
                <a href="/kepala-unit/approval-peminjaman" class="text-xs font-semibold text-blue-600 hover:underline">
                    Buka Pinjaman &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($antreanPinjam as $pinjam)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $pinjam->kode }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Peminjam: {{ $pinjam->unitPeminjam?->nama ?? '-' }} &bull; Pemohon: {{ $pinjam->pengaju?->nama ?? '-' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="blue">Diajukan</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $pinjam->tanggal_pinjam?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada permohonan pinjam alat ruangan yang aktif.
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Akses Cepat Operasional --}}
    <flux:card class="space-y-4 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <flux:heading size="md">Pintasan Wewenang Kepala Ruangan</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <a href="/kepala-unit/approval-permintaan" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="clipboard-document-check" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Approval Amprah</div>
                    <div class="text-[10px] text-zinc-500">Otorisasi Meja 1 Karu</div>
                </div>
            </a>
            <a href="/kepala-unit/approval-peminjaman" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="key" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Izin Pinjam Aset</div>
                    <div class="text-[10px] text-zinc-500">Persetujuan pinjam fasilitas</div>
                </div>
            </a>
            <a href="/kepala-unit/stok-ruangan" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="cube" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Persediaan Ruangan</div>
                    <div class="text-[10px] text-zinc-500">Stok lokal BHP & aset</div>
                </div>
            </a>
            <a href="/kepala-unit/lapor-servis" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="wrench" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Lapor Servis</div>
                    <div class="text-[10px] text-zinc-500">Pemeliharaan alat rusak</div>
                </div>
            </a>
        </div>
    </flux:card>
</div>
