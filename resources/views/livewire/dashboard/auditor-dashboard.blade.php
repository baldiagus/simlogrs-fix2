<div class="space-y-6">
    {{-- Header Dashboard --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Dashboard Auditor Internal (SPI)</flux:heading>
            <flux:subheading>Forensik audit trail, kepatuhan transaksi logistik, dan evaluasi penyelesaian CAPA.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="rose" inset="top bottom">Pengawas Internal SPI</flux:badge>
            <x-ui.status-badge status="AKTIF" />
        </div>
    </div>

    {{-- Grid 4 KPI Metrik Operasional --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Temuan CAPA Aktif</span>
                <flux:icon name="exclamation-circle" class="size-4 text-rose-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-rose-600 dark:text-rose-400 tabular-nums">
                {{ $totalTemuanAktif }}
            </div>
            <div class="text-[11px] text-zinc-500">Status In Progress / In Review</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Risiko Mayor</span>
                <flux:icon name="shield-exclamation" class="size-4 text-amber-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 tabular-nums">
                {{ $totalMayor }}
            </div>
            <div class="text-[11px] text-zinc-500">Klausul kritis butuh atensi pimpinan</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Closing Rate CAPA</span>
                <flux:icon name="check-badge" class="size-4 text-emerald-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                {{ $closingRate }}%
            </div>
            <div class="text-[11px] text-zinc-500">Tingkat penyelesaian rekomendasi SPI</div>
        </flux:card>

        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Log Forensik Hari Ini</span>
                <flux:icon name="finger-print" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalLogHariIni }}
            </div>
            <div class="text-[11px] text-zinc-500">Mutasi audit tercatat otomatis</div>
        </flux:card>
    </div>

    {{-- 2 Kolom Panel Operasional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Panel Kiri: Register Temuan CAPA Aktif --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="clipboard-document-list" class="size-5 text-rose-500" />
                    <flux:heading size="md">Register Temuan Audit & CAPA</flux:heading>
                </div>
                <a href="/auditor/register-temuan" class="text-xs font-semibold text-blue-600 hover:underline">
                    Daftar CAPA &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($temuanAktif as $temuan)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $temuan->kode ?? ('AUD-' . $temuan->id) }}</div>
                            <div class="text-[11px] text-zinc-500">
                                Auditee: {{ $temuan->unit?->nama ?? 'Unit Logistik' }} &bull; Auditor: {{ $temuan->auditor?->nama ?? '-' }}
                            </div>
                            <div class="text-[10px] text-zinc-400 mt-0.5">{{ Str::limit($temuan->ringkasan ?? 'Uraian temuan kepatuhan', 50) }}</div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="{{ $temuan->status_capa === 'In Review' ? 'blue' : 'amber' }}">
                                {{ $temuan->status_capa }}
                            </flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">Due: {{ $temuan->due_date_capa?->format('d/m/Y') ?? '-' }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Tidak ada temuan audit yang berstatus aktif saat ini.
                    </div>
                @endforelse
            </div>
        </flux:card>

        {{-- Panel Kanan: Log Audit Trail Forensik Digital Terbaru --}}
        <flux:card class="space-y-4 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="finger-print" class="size-5 text-blue-600" />
                    <flux:heading size="md">Audit Trail Mutasi Terbaru</flux:heading>
                </div>
                <a href="/auditor/audit-trail" class="text-xs font-semibold text-blue-600 hover:underline">
                    Buka Forensik &rarr;
                </a>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                @forelse ($logTerbaru as $log)
                    <div class="py-3 flex items-center justify-between gap-3 text-xs">
                        <div>
                            <div class="font-semibold text-zinc-800 dark:text-zinc-200 font-mono">{{ $log->aksi }}</div>
                            <div class="text-[11px] text-zinc-500">
                                User: {{ $log->pengguna?->nama ?? 'Sistem' }} &bull; Peran: {{ $log->peran?->name ?? '-' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:badge size="xs" color="zinc">{{ $log->referensi_jenis ?? 'Entitas' }}</flux:badge>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5">{{ $log->dibuat_pada?->format('H:i:s d/m/Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs text-zinc-500">
                        Belum ada jejak audit trail yang tercatat.
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Akses Cepat Operasional --}}
    <flux:card class="space-y-4 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <flux:heading size="md">Pintasan Pengawasan SPI</flux:heading>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <a href="/auditor/register-temuan" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="clipboard-document-check" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Register CAPA</div>
                    <div class="text-[10px] text-zinc-500">Evaluasi perbaikan temuan</div>
                </div>
            </a>
            <a href="/auditor/audit-trail" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="finger-print" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Forensik Digital</div>
                    <div class="text-[10px] text-zinc-500">Audit trail sebelum vs sesudah</div>
                </div>
            </a>
            <a href="/auditor/selisih-opname" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="scale" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Selisih Opname</div>
                    <div class="text-[10px] text-zinc-500">Pemeriksaan rekonsiliasi stok</div>
                </div>
            </a>
            <a href="/auditor/kepatuhan-pengadaan" class="p-3.5 border border-zinc-200 dark:border-zinc-800 rounded-xl hover:border-blue-500 hover:bg-blue-50/20 dark:hover:bg-blue-950/20 transition-all flex items-center gap-3 group">
                <flux:icon name="document-magnifying-glass" class="size-5 text-zinc-500 group-hover:text-blue-600" />
                <div>
                    <div class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">Kepatuhan Belanja</div>
                    <div class="text-[10px] text-zinc-500">Uji silang PO vs PR vendor</div>
                </div>
            </a>
        </div>
    </flux:card>
</div>
