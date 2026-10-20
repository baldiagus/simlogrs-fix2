<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-800">
        <div>
            <flux:heading size="xl" level="1">Pusat Kendali Super Administrator</flux:heading>
            <flux:subheading>Tata kelola wewenang, parameter sistem, dan pengawasan operasional SIMLOG RS.</flux:subheading>
        </div>

        {{-- Emulasi Konteks Toolbar --}}
        <div class="flex flex-wrap items-center gap-2">
            <flux:modal.trigger name="modal-gudang">
                <flux:button variant="outline" size="sm" icon="building-storefront">
                    Emulasi Gudang
                </flux:button>
            </flux:modal.trigger>

            <flux:modal.trigger name="modal-unit">
                <flux:button variant="outline" size="sm" icon="building-office">
                    Emulasi Ruangan
                </flux:button>
            </flux:modal.trigger>

            @if ($contextManager->isEmulating())
                <flux:button wire:click="confirmResetKonteksGlobal" variant="danger" size="sm">
                    Reset Global
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Grid 4 KPI Metrik Ringkasan Dinamis --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Akun Pegawai --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Akun Pegawai</span>
                <flux:icon name="users" class="size-4 text-blue-600" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalPengguna }}
            </div>
            <div class="text-[11px] text-zinc-500">
                <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ $penggunaAktif }} Aktif</span>
                @if ($penggunaNonaktif > 0)
                    &bull; <span class="text-rose-500 font-semibold">{{ $penggunaNonaktif }} Nonaktif</span>
                @else
                    &bull; 0 Nonaktif
                @endif
            </div>
        </flux:card>

        {{-- Card 2: Wewenang RBAC --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Wewenang RBAC</span>
                <flux:icon name="shield-check" class="size-4 text-indigo-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400 tabular-nums">
                {{ $totalPermission }}
            </div>
            <div class="text-[11px] text-zinc-500">{{ $totalRole }} Peran resmi terdaftar</div>
        </flux:card>

        {{-- Card 3: Log Audit Hari Ini --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Log Audit Hari Ini</span>
                <flux:icon name="finger-print" class="size-4 text-amber-500" />
            </div>
            <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                {{ $totalLogHariIni }}
            </div>
            <div class="text-[11px] text-zinc-500">Transaksi mutasi tercatat</div>
        </flux:card>

        {{-- Card 4: Kesehatan Sistem --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Kesehatan Sistem</span>
                <flux:icon name="cpu-chip" class="size-4 text-emerald-500" />
            </div>
            <div class="text-2xl font-bold flex items-center gap-2 {{ $dbStatus && $redisStatus ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' }}">
                <span class="size-2.5 rounded-full {{ $dbStatus && $redisStatus ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                <span>{{ $dbStatus && $redisStatus ? 'Normal' : 'Gangguan' }}</span>
            </div>
            <div class="text-[11px] text-zinc-500">PostgreSQL & Redis terhubung</div>
        </flux:card>
    </div>

    {{-- Panel Tunggal: 5 Aktivitas Mutasi Terakhir (Audit Trail) --}}
    <flux:card class="space-y-4 p-6 border border-zinc-200 dark:border-zinc-800 shadow-xs">
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center gap-2">
                <flux:icon name="clock" class="size-5 text-blue-600" />
                <flux:heading size="md">5 Aktivitas Mutasi Terakhir (Audit Trail)</flux:heading>
            </div>
            <a href="/super-admin/audit-trail" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                <span>Lihat Semua Audit Trail</span>
                <span>&rarr;</span>
            </a>
        </div>

        <flux:table bleed>
            <flux:table.columns>
                <flux:table.column class="w-36">Waktu</flux:table.column>
                <flux:table.column class="w-48">Operator</flux:table.column>
                <flux:table.column class="w-36">Peran</flux:table.column>
                <flux:table.column class="w-44">Aksi</flux:table.column>
                <flux:table.column>Entitas Target</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($logTerbaru as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="font-mono text-xs text-zinc-500">
                            {{ $log->dibuat_pada?->format('H:i:s d/m/Y') }}
                        </flux:table.cell>
                        <flux:table.cell class="font-semibold text-xs text-zinc-900 dark:text-zinc-100">
                            {{ $log->pengguna?->nama ?? 'Sistem' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="xs" color="zinc">
                                {{ $log->peran?->name ? ucwords(str_replace('_', ' ', $log->peran->name)) : '-' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs text-blue-600 dark:text-blue-400 font-semibold">
                            {{ $log->aksi }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-300">
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $log->referensi_jenis ?? 'Entitas' }}</span>
                            @if ($log->referensi_id)
                                <span class="font-mono text-zinc-400">#{{ $log->referensi_id }}</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-xs text-zinc-500">
                            Belum ada jejak audit trail yang tercatat.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal Pilihan Emulasi Gudang --}}
    <flux:modal name="modal-gudang" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Pilih Gudang Emulasi</flux:heading>
                <flux:subheading>Simulasikan sudut pandang gudang logistik.</flux:subheading>
            </div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
                @forelse ($daftarGudang as $gudang)
                    <button
                        wire:click="setGudangEmulasi({{ $gudang->id }})"
                        x-on:click="$flux.modal('modal-gudang').close()"
                        type="button"
                        class="w-full text-left p-2.5 text-xs font-medium rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-between transition-colors cursor-pointer"
                    >
                        <span>{{ $gudang->nama }}</span>
                        <flux:icon icon="chevron-right" class="w-4 h-4 text-zinc-400" />
                    </button>
                @empty
                    <div class="text-xs text-zinc-500 py-2">Tidak ada gudang aktif.</div>
                @endforelse
            </div>
        </div>
    </flux:modal>

    {{-- Modal Pilihan Emulasi Ruangan --}}
    <flux:modal name="modal-unit" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Pilih Ruangan Emulasi</flux:heading>
                <flux:subheading>Simulasikan sudut pandang unit pelayanan.</flux:subheading>
            </div>
            <div class="space-y-1 max-h-60 overflow-y-auto">
                @forelse ($daftarUnit as $unit)
                    <button
                        wire:click="setUnitEmulasi({{ $unit->id }})"
                        x-on:click="$flux.modal('modal-unit').close()"
                        type="button"
                        class="w-full text-left p-2.5 text-xs font-medium rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 flex items-center justify-between transition-colors cursor-pointer"
                    >
                        <span>{{ $unit->nama }}</span>
                        <flux:icon icon="chevron-right" class="w-4 h-4 text-zinc-400" />
                    </button>
                @empty
                    <div class="text-xs text-zinc-500 py-2">Tidak ada unit aktif.</div>
                @endforelse
            </div>
        </div>
    </flux:modal>

    {{-- Modal Konfirmasi Operasi Sensitif --}}
    <x-ui.confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
