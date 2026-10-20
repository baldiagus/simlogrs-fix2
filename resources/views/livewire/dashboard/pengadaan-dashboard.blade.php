<div class="space-y-6">
    {{-- Header Dashboard --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Dashboard Bagian Pengadaan</flux:heading>
            <flux:subheading>Verifikasi permintaan pengadaan (PR), penerbitan PO rekanan, dan kontrol pagu belanja.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="indigo" inset="top bottom">Pengadaan & Vendor</flux:badge>
            <x-ui.status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Grid 4 KPI Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">PR Siap PO</span>
                <flux:icon name="document-text" class="size-4 text-amber-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 tabular-nums">
                {{ $totalPrSiapPo }}
            </div>
            <div class="text-[11px] text-zinc-500">Permintaan siap diterbitkan PO</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">PO Aktif Berjalan</span>
                <flux:icon name="shopping-cart" class="size-4 text-indigo-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400 tabular-nums">
                {{ $totalPoAktif }}
            </div>
            <div class="text-[11px] text-zinc-500">Pesanan dalam proses kirim vendor</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Serapan Pagu TA 2026</span>
                <span class="text-xs font-bold font-mono {{ $serapanPersen > 75 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $serapanPersen }}%</span>
            </div>
            <div class="text-xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                Rp {{ number_format($totalRealisasi, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-zinc-500">Dari pagu Rp {{ number_format($totalPagu, 0, ',', '.') }}</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Mitra Rekanan</span>
                <flux:icon name="building-office-2" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalVendor }}
            </div>
            <div class="text-[11px] text-zinc-500">Pemasok & vendor terdaftar</div>
        </flux:card>
    </div>

    {{-- 2 Kolom Panel Operasional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Panel Kiri: Antrean PR Siap Diterbitkan PO --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="queue-list" class="size-5 text-amber-500" />
                    <flux:heading size="md">Antrean Permintaan Pengadaan (PR)</flux:heading>
                </div>
                <a href="/pengadaan/permintaan-pengadaan" class="text-xs font-semibold text-blue-600 hover:underline">
                    Daftar PR &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($antreanPr as $item)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $item->nomor_pr ?? ('PR-' . $item->id) }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Pemohon: {{ $item->nama_pemohon }} &bull; Pengaju: {{ $item->pengaju?->nama ?? '-' }}
                            </div>
                            <div class="text-[10px] text-zinc-400 mt-0.5">{{ $item->detail->count() }} item komoditas diajukan</div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="amber">{{ str_replace('_', ' ', $item->status) }}</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $item->tanggal_permintaan?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada antrean permintaan pengadaan yang menunggu penerbitan PO.
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Panel Kanan: PO Aktif Berjalan --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="truck" class="size-5 text-indigo-500" />
                    <flux:heading size="md">Pesanan Pembelian (PO) Aktif</flux:heading>
                </div>
                <a href="/pengadaan/pesanan-pembelian" class="text-xs font-semibold text-blue-600 hover:underline">
                    Daftar PO &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($poAktif as $po)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $po->kode }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Rekanan: {{ $po->pemasok?->nama ?? '-' }} &bull; Nilai: Rp {{ number_format($po->total_biaya ?? 0, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="indigo">{{ str_replace('_', ' ', $po->status) }}</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">Tgl: {{ $po->tanggal_po?->format('d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada pesanan pembelian yang sedang aktif berjalan.
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Akses Cepat Operasional --}}
    <flux:card class="space-y-4 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <flux:heading size="md">Pintasan Kerja Pengadaan</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <a href="/pengadaan/permintaan-pengadaan" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="document-text" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Permintaan (PR)</div>
                    <div class="text-[10px] text-zinc-500">Verifikasi usulan pengadaan</div>
                </div>
            </a>
            <a href="/pengadaan/pesanan-pembelian" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="shopping-bag" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Pesanan (PO)</div>
                    <div class="text-[10px] text-zinc-500">Terbitkan PO ke vendor</div>
                </div>
            </a>
            <a href="/pengadaan/realisasi-anggaran" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="banknotes" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Pagu Anggaran</div>
                    <div class="text-[10px] text-zinc-500">Kendali serapan plafon belanja</div>
                </div>
            </a>
            <a href="/pengadaan/master-rekanan" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="building-office-2" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Katalog Rekanan</div>
                    <div class="text-[10px] text-zinc-500">Evaluasi vendor rekanan</div>
                </div>
            </a>
        </div>
    </flux:card>
</div>
