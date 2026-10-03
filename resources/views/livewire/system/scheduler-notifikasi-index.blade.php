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
                    wire:click="triggerSweepExpired"
                    wire:confirm="Jalankan pemindaian kedaluwarsa sekarang? Seluruh batch persediaan di gudang yang telah melewati tanggal kedaluwarsa akan langsung dikunci statusnya menjadi KEDALUWARSA (dilarang alokasi FEFO)."
                    variant="filled"
                    size="sm"
                    icon="arrow-path"
                    class="w-full justify-center text-xs font-semibold"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="triggerSweepExpired">Jalankan Sweep Kedaluwarsa</span>
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
                    wire:click="triggerAlertOverdue"
                    wire:confirm="Jalankan pemeriksaan keterlambatan peminjaman aset sekarang? Notifikasi peringatan akan langsung diterbitkan kepada staf peminjam dan kepala unit penanggung jawab."
                    variant="filled"
                    size="sm"
                    icon="bell-alert"
                    class="w-full justify-center text-xs font-semibold"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="triggerAlertOverdue">Jalankan Cek Overdue</span>
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
                <div class="grid grid-cols-1 gap-2 text-xs">
                    <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200/80 dark:border-zinc-700/80 flex items-center justify-between">
                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium">Tiket SPK Diterbitkan</div>
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
                    wire:click="triggerGeneratePreventive"
                    wire:confirm="Jalankan penerbitan tiket servis preventif sekarang? Jadwal pemeliharaan berkala aktif yang telah jatuh tempo akan langsung dibuatkan tiket SPK resmi."
                    variant="filled"
                    size="sm"
                    icon="wrench"
                    class="w-full justify-center text-xs font-semibold"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="triggerGeneratePreventive">Generate Tiket Preventif</span>
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

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th scope="col" class="py-2.5 px-3 font-semibold">Waktu Terbit</th>
                        <th scope="col" class="py-2.5 px-3 font-semibold">Penerima</th>
                        <th scope="col" class="py-2.5 px-3 font-semibold">Judul Notifikasi</th>
                        <th scope="col" class="py-2.5 px-3 font-semibold">Isi Pesan</th>
                        <th scope="col" class="py-2.5 px-3 font-semibold text-center">Status Baca</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($daftarNotifikasi as $notif)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                            <td class="py-2.5 px-3 font-mono text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $notif->dibuat_pada ? $notif->dibuat_pada->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 font-medium text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                {{ $notif->pengguna?->nama ?? 'User #' . $notif->pengguna_id }}
                            </td>
                            <td class="py-2.5 px-3 font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $notif->judul }}
                            </td>
                            <td class="py-2.5 px-3 text-zinc-600 dark:text-zinc-400">
                                <div class="max-w-md truncate" title="{{ $notif->pesan }}">
                                    {{ $notif->pesan }}
                                </div>
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
