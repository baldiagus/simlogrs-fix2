<div class="space-y-6">
    {{-- Header & Quick Trigger Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Task Scheduler & Cron Monitor</flux:heading>
            <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Pusat pemantauan tugas otomatis terjadwal (00:01, 01:00, 06:00 WIB) dan kontrol manual pemicu task.
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button
                wire:click="triggerSweepExpired"
                variant="filled"
                size="sm"
                icon="arrow-path"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="triggerSweepExpired">Sweep Kedaluwarsa</span>
                <span wire:loading wire:target="triggerSweepExpired">Menjalankan...</span>
            </flux:button>

            <flux:button
                wire:click="triggerAlertOverdue"
                variant="filled"
                size="sm"
                icon="bell-alert"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="triggerAlertOverdue">Cek Overdue Pinjaman</span>
                <span wire:loading wire:target="triggerAlertOverdue">Memeriksa...</span>
            </flux:button>

            <flux:button
                wire:click="triggerGeneratePreventive"
                variant="filled"
                size="sm"
                icon="wrench"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="triggerGeneratePreventive">Generate Preventif</span>
                <span wire:loading wire:target="triggerGeneratePreventive">Menerbitkan...</span>
            </flux:button>
        </div>
    </div>

    {{-- Grid 3 Kartu Task Cron Terjadwal --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        {{-- Task 1: Sweep Expired Batches --}}
        <flux:card class="space-y-4 border-t-4 border-t-amber-500 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-[11px] font-mono font-bold tracking-wider text-amber-600 dark:text-amber-400 uppercase">
                            Cron Harian (00:01 WIB)
                        </div>
                        <flux:heading size="base" class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            Sweep Expired Batches
                        </flux:heading>
                    </div>
                    <flux:badge size="sm" :color="($lastSweep['status'] ?? '') === 'Sukses' ? 'emerald' : 'zinc'">
                        {{ $lastSweep['status'] ?? 'Pending' }}
                    </flux:badge>
                </div>

                <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    Memindai batch persediaan di gudang. Mengunci status batch kedaluwarsa menjadi <span class="font-semibold text-rose-600">KEDALUWARSA</span> (dilarang FEFO) dan menandai batch &le; 90 hari menjadi <span class="font-semibold text-amber-600">HAMPIR_KEDALUWARSA</span>.
                </p>

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
                    wire:click="triggerSweepExpired"
                    variant="ghost"
                    size="xs"
                    class="w-full justify-center text-xs font-semibold"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="triggerSweepExpired">Jalankan Task Ini Sekarang</span>
                    <span wire:loading wire:target="triggerSweepExpired">Sedang Berjalan...</span>
                </flux:button>
            </div>
        </flux:card>

        {{-- Task 2: Alert Overdue Loans --}}
        <flux:card class="space-y-4 border-t-4 border-t-rose-500 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-[11px] font-mono font-bold tracking-wider text-rose-600 dark:text-rose-400 uppercase">
                            Cron Harian (06:00 WIB)
                        </div>
                        <flux:heading size="base" class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            Alert Overdue Loans
                        </flux:heading>
                    </div>
                    <flux:badge size="sm" :color="($lastOverdue['status'] ?? '') === 'Sukses' ? 'emerald' : 'zinc'">
                        {{ $lastOverdue['status'] ?? 'Pending' }}
                    </flux:badge>
                </div>

                <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    Mendeteksi aset sarana yang melewati batas tanggal rencana pengembalian. Otomatis menerbitkan notifikasi lonceng internal kepada staf peminjam dan kepala unit penanggung jawab.
                </p>

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
                    wire:click="triggerAlertOverdue"
                    variant="ghost"
                    size="xs"
                    class="w-full justify-center text-xs font-semibold"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="triggerAlertOverdue">Jalankan Task Ini Sekarang</span>
                    <span wire:loading wire:target="triggerAlertOverdue">Sedang Berjalan...</span>
                </flux:button>
            </div>
        </flux:card>

        {{-- Task 3: Generate Preventive Maintenance --}}
        <flux:card class="space-y-4 border-t-4 border-t-indigo-500 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-[11px] font-mono font-bold tracking-wider text-indigo-600 dark:text-indigo-400 uppercase">
                            Cron Harian (01:00 WIB)
                        </div>
                        <flux:heading size="base" class="font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            Preventive Maintenance
                        </flux:heading>
                    </div>
                    <flux:badge size="sm" :color="($lastPreventive['status'] ?? '') === 'Sukses' ? 'emerald' : 'zinc'">
                        {{ $lastPreventive['status'] ?? 'Pending' }}
                    </flux:badge>
                </div>

                <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    Memindai jadwal servis berkala aktif yang telah tiba waktunya. Menerbitkan tiket servis preventif IPSRS baru, menerbitkan SPK jika teknisi telah ditetapkan, dan memajukan tanggal siklus berikutnya.
                </p>

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
                    wire:click="triggerGeneratePreventive"
                    variant="ghost"
                    size="xs"
                    class="w-full justify-center text-xs font-semibold"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="triggerGeneratePreventive">Jalankan Task Ini Sekarang</span>
                    <span wire:loading wire:target="triggerGeneratePreventive">Sedang Berjalan...</span>
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

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="py-2.5 px-3 font-semibold">Waktu Terbit</th>
                        <th class="py-2.5 px-3 font-semibold">Penerima</th>
                        <th class="py-2.5 px-3 font-semibold">Judul Notifikasi</th>
                        <th class="py-2.5 px-3 font-semibold">Isi Pesan</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Status Baca</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($daftarNotifikasi as $notif)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                            <td class="py-2.5 px-3 font-mono text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $notif->dibuat_pada ? $notif->dibuat_pada->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 font-medium text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                {{ $notif->pengguna?->name ?? 'User #' . $notif->pengguna_id }}
                            </td>
                            <td class="py-2.5 px-3 font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $notif->judul }}
                            </td>
                            <td class="py-2.5 px-3 text-zinc-600 dark:text-zinc-400 max-w-md line-clamp-1" title="{{ $notif->pesan }}">
                                {{ $notif->pesan }}
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                @if ($notif->dibaca)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">Dibaca</span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Belum Dibaca</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-zinc-500 text-xs">
                                Belum ada notifikasi internal yang diterbitkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
