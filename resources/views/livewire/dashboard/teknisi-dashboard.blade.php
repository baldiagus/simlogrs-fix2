<div class="space-y-6">
    {{-- Header Dashboard --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">{{ $portalTitle }}</flux:heading>
            <flux:subheading>{{ $portalSubtitle }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="violet" inset="top bottom">{{ $isKepalaTeknisi ? 'Koordinator IPSRS' : 'Teknisi Pelaksana' }}</flux:badge>
            <x-ui.status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Grid 4 KPI Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">CITO / Darurat</span>
                <flux:icon name="exclamation-circle" class="size-4 text-rose-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-rose-600 dark:text-rose-400 tabular-nums">
                {{ $totalTiketMendesak }}
            </div>
            <div class="text-[11px] text-zinc-500">Tiket prioritas CITO / Tinggi aktif</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">SPK Aktif</span>
                <flux:icon name="wrench-screwdriver" class="size-4 text-violet-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-violet-600 dark:text-violet-400 tabular-nums">
                {{ $totalSpkAktif }}
            </div>
            <div class="text-[11px] text-zinc-500">{{ $isKepalaTeknisi ? 'Total penugasan aktif tim' : 'SPK sedang Anda kerjakan' }}</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Menunggu SPK</span>
                <flux:icon name="clipboard-document-list" class="size-4 text-amber-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 tabular-nums">
                {{ $totalMenungguSpk }}
            </div>
            <div class="text-[11px] text-zinc-500">Laporan belum diterbitkan SPK</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Jadwal Preventif</span>
                <flux:icon name="calendar" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalPreventif }}
            </div>
            <div class="text-[11px] text-zinc-500">Servis rutin & pemeliharaan berkala</div>
        </flux:card>
    </div>

    {{-- 2 Kolom Panel Operasional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Panel Kiri: Tiket CITO & Mendesak --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="fire" class="size-5 text-rose-500" />
                    <flux:heading size="md">Tiket Servis Mendesak (CITO / Tinggi)</flux:heading>
                </div>
                <a href="/teknisi/lapor-servis" class="text-xs font-semibold text-blue-600 hover:underline">
                    Daftar Tiket &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($tiketMendesak as $tiket)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $tiket->kode }}</div>
                            <div class="text-[11px] text-zinc-500">
                                {{ $tiket->aset?->nama ?? 'Aset Fisik' }} &bull; Unit: {{ $tiket->unit?->nama ?? '-' }}
                            </div>
                            <div class="text-[10px] text-zinc-400 mt-0.5">{{ Str::limit($tiket->deskripsi_masalah ?? 'Kendala fasilitas', 50) }}</div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="rose">{{ $tiket->prioritas }}</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $tiket->dibuat_pada?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada tiket servis CITO atau darurat saat ini.
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Panel Kanan: SPK Aktif Berjalan --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="wrench" class="size-5 text-violet-500" />
                    <flux:heading size="md">{{ $isKepalaTeknisi ? 'Penugasan SPK Tim Aktif' : 'Lembar Kerja SPK Anda' }}</flux:heading>
                </div>
                <a href="/teknisi/spk" class="text-xs font-semibold text-blue-600 hover:underline">
                    Daftar SPK &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($spkAktif as $spk)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $spk->nomor_spk ?? ('SPK-' . $spk->id) }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Alat: {{ $spk->permintaanMaintenance?->aset?->nama ?? '-' }} &bull; Teknisi: {{ $spk->teknisi?->nama ?? '-' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="violet">{{ str_replace('_', ' ', $spk->status) }}</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">Target: {{ $spk->target_selesai?->format('d/m/Y') ?? '-' }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada SPK perbaikan yang sedang berjalan.
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Akses Cepat Operasional --}}
    <flux:card class="space-y-4 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <flux:heading size="md">Pintasan Fasilitas & IPSRS</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <a href="/teknisi/spk" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="wrench-screwdriver" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Disposisi SPK</div>
                    <div class="text-[10px] text-zinc-500">Penugasan & status servis</div>
                </div>
            </a>
            <a href="/teknisi/lembar-kerja" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="pencil-square" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Lembar Kerja</div>
                    <div class="text-[10px] text-zinc-500">Input pengerjaan teknisi</div>
                </div>
            </a>
            <a href="/teknisi/pemeliharaan-berkala" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="calendar" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Jadwal Preventif</div>
                    <div class="text-[10px] text-zinc-500">Pemeliharaan & kalibrasi</div>
                </div>
            </a>
            <a href="/teknisi/rekam-medis-aset" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="document-chart-bar" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Rekam Medis Aset</div>
                    <div class="text-[10px] text-zinc-500">Riwayat histori servis alat</div>
                </div>
            </a>
        </div>
    </flux:card>
</div>
