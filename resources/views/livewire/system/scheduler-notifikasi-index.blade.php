<div class="space-y-6">
    {{-- Header & Status Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Task Scheduler & Cron Monitor</flux:heading>
            <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Pusat pemantauan tugas otomatis terjadwal (00:01, 01:00, 06:00 WIB) dan kontrol manual pemicu task.
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:badge size="sm" color="emerald" icon="check-circle">
                Jadwal Otomatis Siap (WIB)
            </flux:badge>
        </div>
    </div>

    {{-- Grid 3 Kartu Task Cron Terjadwal --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        {{-- Task 1: Sweep Expired Batches --}}
        <flux:card class="space-y-4 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <flux:heading size="base" class="font-bold text-zinc-900 dark:text-zinc-100">
                            Sweep Expired Batches
                        </flux:heading>
                        <div class="text-xs font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Harian &bull; 00:01 WIB
                        </div>
                    </div>
                    <flux:badge size="sm" :color="($lastSweep['status'] ?? '') === 'Sukses' ? 'emerald' : 'zinc'">
                        {{ ($lastSweep['status'] ?? '') === 'Sukses' ? 'Sukses' : 'Siap' }}
                    </flux:badge>
                </div>

                <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    Memindai batch persediaan di gudang. Mengunci status batch kedaluwarsa menjadi <span class="font-semibold text-rose-600">KEDALUWARSA</span> (dilarang FEFO) dan menandai batch &le; 90 hari menjadi <span class="font-semibold text-amber-600">HAMPIR_KEDALUWARSA</span>.
                </p>

                {{-- Metric Chips --}}
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/80">
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium">Batch Terkunci</div>
                        <div class="text-base font-bold font-mono text-rose-600 dark:text-rose-400">{{ $lastSweep['expired_count'] ?? 0 }}</div>
                    </div>
                    <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/80">
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium">Pantauan &le;90 Hari</div>
                        <div class="text-base font-bold font-mono text-amber-600 dark:text-amber-400">{{ $lastSweep['warning_count'] ?? 0 }}</div>
                    </div>
                </div>

                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/80 text-xs space-y-1.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-zinc-500 dark:text-zinc-400">Eksekusi Terakhir:</span>
                        <span class="font-mono font-medium text-zinc-800 dark:text-zinc-200">{{ $lastSweep['waktu'] }}</span>
                    </div>
                    <div class="pt-1 border-t border-zinc-200/60 dark:border-zinc-700/60 text-[11px] text-zinc-700 dark:text-zinc-300">
                        <span class="font-medium">Hasil:</span> {{ $lastSweep['ringkasan'] }}
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button
                    wire:click="confirmSweepExpired"
                    variant="filled"
                    size="sm"
                    icon="arrow-path"
                    class="w-full justify-center text-xs font-semibold"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="triggerSweepExpired,confirmSweepExpired">Jalankan Sweep Kedaluwarsa</span>
                    <span wire:loading wire:target="triggerSweepExpired">Menjalankan...</span>
                </flux:button>
            </div>
        </flux:card>

        {{-- Task 2: Alert Overdue Loans --}}
        <flux:card class="space-y-4 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <flux:heading size="base" class="font-bold text-zinc-900 dark:text-zinc-100">
                            Alert Overdue Loans
                        </flux:heading>
                        <div class="text-xs font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Harian &bull; 06:00 WIB
                        </div>
                    </div>
                    <flux:badge size="sm" :color="($lastOverdue['status'] ?? '') === 'Sukses' ? 'emerald' : 'zinc'">
                        {{ ($lastOverdue['status'] ?? '') === 'Sukses' ? 'Sukses' : 'Siap' }}
                    </flux:badge>
                </div>

                <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    Mendeteksi aset sarana yang melewati batas tanggal rencana pengembalian. Otomatis menerbitkan notifikasi lonceng internal kepada staf peminjam dan kepala unit penanggung jawab.
                </p>

                {{-- Metric Chips --}}
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/80">
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium">Pinjaman Overdue</div>
                        <div class="text-base font-bold font-mono text-rose-600 dark:text-rose-400">{{ $lastOverdue['overdue_count'] ?? 0 }}</div>
                    </div>
                    <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/80">
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium">Notifikasi Terbit</div>
                        <div class="text-base font-bold font-mono text-blue-600 dark:text-blue-400">{{ $lastOverdue['notif_count'] ?? 0 }}</div>
                    </div>
                </div>

                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/80 text-xs space-y-1.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-zinc-500 dark:text-zinc-400">Eksekusi Terakhir:</span>
                        <span class="font-mono font-medium text-zinc-800 dark:text-zinc-200">{{ $lastOverdue['waktu'] }}</span>
                    </div>
                    <div class="pt-1 border-t border-zinc-200/60 dark:border-zinc-700/60 text-[11px] text-zinc-700 dark:text-zinc-300">
                        <span class="font-medium">Hasil:</span> {{ $lastOverdue['ringkasan'] }}
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button
                    wire:click="confirmAlertOverdue"
                    variant="filled"
                    size="sm"
                    icon="bell-alert"
                    class="w-full justify-center text-xs font-semibold"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="triggerAlertOverdue,confirmAlertOverdue">Jalankan Cek Overdue</span>
                    <span wire:loading wire:target="triggerAlertOverdue">Memeriksa...</span>
                </flux:button>
            </div>
        </flux:card>

        {{-- Task 3: Generate Preventive Maintenance --}}
        <flux:card class="space-y-4 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <flux:heading size="base" class="font-bold text-zinc-900 dark:text-zinc-100">
                            Preventive Maintenance
                        </flux:heading>
                        <div class="text-xs font-mono text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Harian &bull; 01:00 WIB
                        </div>
                    </div>
                    <flux:badge size="sm" :color="($lastPreventive['status'] ?? '') === 'Sukses' ? 'emerald' : 'zinc'">
                        {{ ($lastPreventive['status'] ?? '') === 'Sukses' ? 'Sukses' : 'Siap' }}
                    </flux:badge>
                </div>

                <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    Memindai jadwal servis berkala aktif yang telah tiba waktunya. Menerbitkan tiket servis preventif IPSRS baru, menerbitkan SPK jika teknisi telah ditetapkan, dan memajukan tanggal siklus berikutnya.
                </p>

                {{-- Metric Chips --}}
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/80">
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium">Jadwal Siap Servis</div>
                        <div class="text-base font-bold font-mono text-amber-600 dark:text-amber-400">{{ $jadwalSiapCount }}</div>
                    </div>
                    <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/80">
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium">Tiket SPK Terbit</div>
                        <div class="text-base font-bold font-mono text-indigo-600 dark:text-indigo-400">{{ $lastPreventive['created_count'] ?? 0 }}</div>
                    </div>
                </div>

                <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/80 text-xs space-y-1.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-zinc-500 dark:text-zinc-400">Eksekusi Terakhir:</span>
                        <span class="font-mono font-medium text-zinc-800 dark:text-zinc-200">{{ $lastPreventive['waktu'] }}</span>
                    </div>
                    <div class="pt-1 border-t border-zinc-200/60 dark:border-zinc-700/60 text-[11px] text-zinc-700 dark:text-zinc-300">
                        <span class="font-medium">Hasil:</span> {{ $lastPreventive['ringkasan'] }}
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button
                    wire:click="confirmGeneratePreventive"
                    variant="filled"
                    size="sm"
                    icon="wrench"
                    class="w-full justify-center text-xs font-semibold"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="triggerGeneratePreventive,confirmGeneratePreventive">Generate Tiket Preventif</span>
                    <span wire:loading wire:target="triggerGeneratePreventive">Menerbitkan...</span>
                </flux:button>
            </div>
        </flux:card>
    </div>

    {{-- Riwayat Notifikasi Lonceng Internal Terbaru --}}
    <flux:card class="space-y-4 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Log Notifikasi Internal Terbitan Scheduler</flux:heading>
                <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400">
                    10 notifikasi lonceng terakhir yang diterbitkan oleh sistem atau task otomatis.
                </flux:subheading>
            </div>
            <flux:badge size="sm" color="zinc">{{ $daftarNotifikasi->count() }} Terdata</flux:badge>
        </div>

        <flux:table bleed container:class="mt-4">
            <flux:table.columns sticky>
                <flux:table.column class="w-32">Waktu Terbit</flux:table.column>
                <flux:table.column class="w-48">Penerima</flux:table.column>
                <flux:table.column class="w-56">Judul Notifikasi</flux:table.column>
                <flux:table.column>Isi Pesan</flux:table.column>
                <flux:table.column class="text-center w-28">Status</flux:table.column>
                <flux:table.column class="text-right w-16">Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($daftarNotifikasi as $notif)
                    <flux:table.row :key="$notif->id">
                        <flux:table.cell class="font-mono text-zinc-600 dark:text-zinc-400 text-xs whitespace-nowrap">
                            {{ $notif->dibuat_pada ? $notif->dibuat_pada->format('d/m/Y H:i') : '-' }}
                        </flux:table.cell>
                        <flux:table.cell style="white-space: normal;">
                            <span class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $notif->pengguna?->nama ?? 'User #' . $notif->pengguna_id }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell style="white-space: normal;">
                            <button
                                type="button"
                                wire:click="showDetailNotif({{ $notif->id }})"
                                class="text-left font-semibold text-xs text-zinc-800 dark:text-zinc-200 hover:text-blue-600 dark:hover:text-blue-400 transition-colors line-clamp-1"
                                title="Klik untuk membaca pesan lengkap"
                            >
                                {{ $notif->judul }}
                            </button>
                        </flux:table.cell>
                        <flux:table.cell style="white-space: normal;">
                            <div class="line-clamp-2 text-xs text-zinc-600 dark:text-zinc-400" title="{{ $notif->pesan }}">
                                {{ $notif->pesan }}
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-center w-28">
                            @if ($notif->dibaca)
                                <flux:badge size="sm" color="zinc">Dibaca</flux:badge>
                            @else
                                <flux:badge size="sm" color="blue">Belum Dibaca</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right w-16">
                            <flux:button
                                wire:click="showDetailNotif({{ $notif->id }})"
                                size="xs"
                                variant="ghost"
                                icon="eye"
                                title="Lihat Notifikasi"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <x-empty-state message="Belum ada notifikasi internal yang diterbitkan." cols="6" />
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- Modal Detail Notifikasi --}}
    <flux:modal name="modal-detail-notifikasi" wire:model="showDetailNotifModal" class="md:w-[32rem]">
        @if ($selectedNotifikasi)
            <div class="space-y-4">
                <div class="pb-3 border-b border-zinc-200 dark:border-zinc-800" style="padding-right: 2.5rem;">
                    <flux:heading size="lg">{{ $selectedNotifikasi->judul }}</flux:heading>
                    <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Waktu Terbit: {{ $selectedNotifikasi->dibuat_pada?->format('d F Y H:i:s') }} WIB
                    </flux:subheading>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 font-medium">Penerima:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedNotifikasi->pengguna?->nama ?? '-' }}</div>
                    </div>

                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 font-medium">Status Baca:</span>
                        <div class="mt-1">
                            @if ($selectedNotifikasi->dibaca)
                                <flux:badge size="sm" color="zinc">Sudah Dibaca</flux:badge>
                            @else
                                <flux:badge size="sm" color="blue">Belum Dibaca</flux:badge>
                            @endif
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60">
                        <span class="text-zinc-500 dark:text-zinc-400 font-medium block mb-1">Isi Pesan Lengkap:</span>
                        <p class="text-zinc-800 dark:text-zinc-200 leading-relaxed text-xs">{{ $selectedNotifikasi->pesan }}</p>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Modal Konfirmasi Aksi Scheduler --}}
    <x-confirm-modal
        :title="$confirmTitle"
        :description="$confirmDescription"
        :confirmText="$confirmButtonText"
        :variant="$confirmButtonVariant"
    />
</div>
