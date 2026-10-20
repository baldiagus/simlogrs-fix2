<div class="space-y-6">
    {{-- Header Dashboard --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Dashboard Petugas / Staf Ruangan</flux:heading>
            <flux:subheading>Pengajuan amprah barang, konfirmasi penerimaan distribusi, dan peminjaman fasilitas.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="teal" inset="top bottom">Staf Operasional Ruangan</flux:badge>
            <x-ui.status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Grid 4 KPI Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Menunggu Terima (RCV)</span>
                <flux:icon name="arrow-down-tray" class="size-4 text-emerald-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                {{ $totalAntreanRcv }}
            </div>
            <div class="text-[11px] text-zinc-500">Paket SPPB tiba siap konfirmasi</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Amprah Berjalan</span>
                <flux:icon name="clock" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-blue-600 dark:text-blue-400 tabular-nums">
                {{ $totalAmprahBerjalan }}
            </div>
            <div class="text-[11px] text-zinc-500">Dalam proses persetujuan / kirim</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Aset Dipinjamkan</span>
                <flux:icon name="key" class="size-4 text-amber-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalAsetDipinjam }}
            </div>
            <div class="text-[11px] text-zinc-500">Unit alat dipakai ruangan lain</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Lapor Servis</span>
                <flux:icon name="wrench" class="size-4 text-zinc-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalLaporServis }}
            </div>
            <div class="text-[11px] text-zinc-500">Tiket perbaikan alat aktif</div>
        </flux:card>
    </div>

    {{-- 2 Kolom Panel Operasional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Panel Kiri: Konfirmasi Penerimaan Distribusi (RCV) --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="inbox" class="size-5 text-emerald-500" />
                    <flux:heading size="md">Paket Tiba Menunggu Terima (RCV)</flux:heading>
                </div>
                <a href="/petugas-unit/penerimaan-barang" class="text-xs font-semibold text-blue-600 hover:underline">
                    Konfirmasi RCV &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($antreanRcv as $sppb)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $sppb->nomor_sppb ?? ('SPPB-' . $sppb->id) }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Asal: {{ $sppb->gudang?->nama ?? 'Gudang Sentral' }} &bull; Ref Amprah: {{ $sppb->permintaanDistribusi?->kode ?? '-' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="emerald">Tiba / Dikirim</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $sppb->tanggal_kirim?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada pengiriman distribusi yang menunggu konfirmasi penerimaan.
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Panel Kanan: Status Amprah Terakhir --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="document-text" class="size-5 text-blue-600" />
                    <flux:heading size="md">Permintaan Amprah Berjalan</flux:heading>
                </div>
                <a href="/petugas-unit/permintaan-barang" class="text-xs font-semibold text-blue-600 hover:underline">
                    Daftar Amprah &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($amprahBerjalan as $amprah)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $amprah->kode }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Tujuan: {{ $amprah->gudang?->nama ?? '-' }} &bull; {{ $amprah->detail->count() }} jenis barang
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="blue">{{ str_replace('_', ' ', $amprah->status) }}</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $amprah->dibuat_pada?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada permohonan amprah yang sedang berjalan.
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Akses Cepat Operasional --}}
    <flux:card class="space-y-4 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <flux:heading size="md">Pintasan Kerja Petugas Ruangan</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <a href="/petugas-unit/permintaan-barang" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="plus-circle" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Buat Amprah</div>
                    <div class="text-[10px] text-zinc-500">Pengajuan kebutuhan rutin</div>
                </div>
            </a>
            <a href="/petugas-unit/penerimaan-barang" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="inbox-arrow-down" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Konfirmasi Terima</div>
                    <div class="text-[10px] text-zinc-500">Terima paket kiriman gudang</div>
                </div>
            </a>
            <a href="/petugas-unit/peminjaman-aset" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="key" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Peminjaman Aset</div>
                    <div class="text-[10px] text-zinc-500">Pinjam alat antar ruangan</div>
                </div>
            </a>
            <a href="/petugas-unit/lapor-servis" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="wrench" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Lapor Kerusakan</div>
                    <div class="text-[10px] text-zinc-500">Lapor servis sarana IPSRS</div>
                </div>
            </a>
        </div>
    </flux:card>
</div>
