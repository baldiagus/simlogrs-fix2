<div class="space-y-6">
    {{-- Header & Filter Tahun --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Pagu & Realisasi Anggaran Belanja Logistik</flux:heading>
            <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Monitoring serapan pagu belanja non-medis rumah sakit, evaluasi sisa kas anggaran, dan komitmen pesanan PO.
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">Tahun Anggaran:</span>
            <select
                wire:model.live="filterTahun"
                class="h-9 text-xs font-semibold rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
                <option value="2026">TA 2026 (Tahun Berjalan)</option>
                <option value="2025">TA 2025 (Arsip)</option>
            </select>
        </div>
    </div>

    {{-- 4 Kartu Ringkasan Finansial Eksekutif --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Pagu --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Pagu Anggaran</span>
                <flux:icon name="banknotes" class="size-4 text-blue-600" />
            </div>
            <div class="text-xl font-bold font-mono text-zinc-900 dark:text-zinc-100">
                Rp {{ number_format($totalPagu, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-zinc-500">Pagu belanja resmi TA {{ $filterTahun }}</div>
        </flux:card>

        {{-- Realisasi Belanja --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Realisasi Terikat (PO)</span>
                <flux:icon name="check-circle" class="size-4 text-emerald-600" />
            </div>
            <div class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400">
                Rp {{ number_format($totalRealisasi, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-zinc-500">Beban transaksi pesanan disahkan</div>
        </flux:card>

        {{-- Sisa Kas Pagu --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Sisa Pagu Tersedia</span>
                <flux:icon name="scale" class="size-4 text-amber-600" />
            </div>
            <div class="text-xl font-bold font-mono text-zinc-900 dark:text-zinc-100">
                Rp {{ number_format($totalSisa, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-zinc-500">Kapasitas belanja tersisa</div>
        </flux:card>

        {{-- Rasio Serapan --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Rata-rata Serapan</span>
                <span class="text-xs font-bold font-mono {{ $serapanPersen > 75 ? 'text-amber-600' : 'text-blue-600' }}">{{ $serapanPersen }}%</span>
            </div>
            <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2.5 overflow-hidden">
                <div
                    class="h-2.5 rounded-full transition-all duration-300 {{ $serapanPersen > 90 ? 'bg-rose-500' : ($serapanPersen > 75 ? 'bg-amber-500' : 'bg-blue-600') }}"
                    style="width: {{ min(100, $serapanPersen) }}%"
                ></div>
            </div>
            <div class="text-[11px] text-zinc-500">
                Status: <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $serapanPersen > 100 ? 'Over-Budget' : ($serapanPersen > 75 ? 'Perhatian / Waspada' : 'Normal') }}</span>
            </div>
        </flux:card>
    </div>

    {{-- Tabel Pos Anggaran --}}
    <flux:card class="space-y-4 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <flux:heading size="lg">Rincian Pos Anggaran Belanja</flux:heading>
                <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400">
                    Alokasi plafon kas belanja logistik non-medis rumah sakit per pos akun.
                </flux:subheading>
            </div>
            <div class="w-full sm:w-64">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari kode atau nama pos..."
                    class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="py-2.5 px-3 font-semibold">Kode Pos</th>
                        <th class="py-2.5 px-3 font-semibold">Pos Anggaran Belanja</th>
                        <th class="py-2.5 px-3 font-semibold text-right">Pagu (Rp)</th>
                        <th class="py-2.5 px-3 font-semibold text-right">Realisasi (Rp)</th>
                        <th class="py-2.5 px-3 font-semibold text-right">Sisa Pagu (Rp)</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Serapan (%)</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Status</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($items as $anggaran)
                        @php
                            $pagu = (float) $anggaran->pagu;
                            $realisasi = (float) $anggaran->realisasi;
                            $sisa = max(0, $pagu - $realisasi);
                            $persen = $pagu > 0 ? round(($realisasi / $pagu) * 100, 1) : 0;
                            $statusColor = match($anggaran->status) {
                                'Normal' => 'emerald',
                                'Waspada' => 'amber',
                                'Over-Budget' => 'rose',
                                default => 'zinc',
                            };
                        @endphp
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30" wire:key="pos-{{ $anggaran->id }}">
                            <td class="py-2.5 px-3 font-mono font-semibold text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                                {{ $anggaran->kode }}
                            </td>
                            <td class="py-2.5 px-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $anggaran->pos_anggaran }}
                            </td>
                            <td class="py-2.5 px-3 font-mono text-right text-zinc-700 dark:text-zinc-300 tabular-nums">
                                Rp {{ number_format($pagu, 0, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 font-mono text-right text-emerald-600 dark:text-emerald-400 font-semibold tabular-nums">
                                Rp {{ number_format($realisasi, 0, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 font-mono text-right text-zinc-800 dark:text-zinc-200 font-bold tabular-nums">
                                Rp {{ number_format($sisa, 0, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <div class="w-16 bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full {{ $persen > 75 ? 'bg-amber-500' : 'bg-blue-600' }}" style="width: {{ min(100, $persen) }}%"></div>
                                    </div>
                                    <span class="font-mono text-[11px] font-semibold text-zinc-700 dark:text-zinc-300">{{ $persen }}%</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <flux:badge size="sm" :color="$statusColor">{{ $anggaran->status }}</flux:badge>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <flux:button
                                    wire:click="openDetail({{ $anggaran->id }})"
                                    size="xs"
                                    variant="ghost"
                                    icon="eye"
                                    title="Lihat Beban Transaksi"
                                />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-zinc-500 text-xs">
                                Belum ada pos anggaran belanja yang terdaftar untuk TA {{ $filterTahun }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Modal Detail Penelusuran Beban PO --}}
    <flux:modal name="modal-detail-anggaran" wire:model="showDetailModal" class="md:w-[42rem]">
        @if ($selectedAnggaran)
            @php
                $paguModal = (float) $selectedAnggaran->pagu;
                $realisasiModal = (float) $selectedAnggaran->realisasi;
                $sisaModal = max(0, $paguModal - $realisasiModal);
                $persenModal = $paguModal > 0 ? round(($realisasiModal / $paguModal) * 100, 1) : 0;
            @endphp
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="text-[11px] font-mono text-blue-600 dark:text-blue-400 font-bold uppercase">
                            {{ $selectedAnggaran->kode }} &bull; TA {{ $selectedAnggaran->tahun }}
                        </div>
                        <flux:heading size="lg" class="mt-0.5">{{ $selectedAnggaran->pos_anggaran }}</flux:heading>
                    </div>
                    <flux:badge size="sm" :color="$selectedAnggaran->status === 'Normal' ? 'emerald' : 'amber'">
                        {{ $selectedAnggaran->status }}
                    </flux:badge>
                </div>

                <div class="grid grid-cols-3 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Pagu Penuh:</span>
                        <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            Rp {{ number_format($paguModal, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Realisasi Terikat:</span>
                        <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">
                            Rp {{ number_format($realisasiModal, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 text-[11px]">Sisa Anggaran:</span>
                        <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                            Rp {{ number_format($sisaModal, 0, ',', '.') }} ({{ $persenModal }}%)
                        </div>
                    </div>
                </div>

                {{-- Daftar PO Terkait --}}
                <div class="space-y-2">
                    <flux:heading size="sm">Daftar Transaksi Pesanan Pembelian (PO) Terikat</flux:heading>
                    <div class="overflow-x-auto max-h-56 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2 px-3">Kode PO</th>
                                    <th class="py-2 px-3">Rekanan Vendor</th>
                                    <th class="py-2 px-3">Tanggal PO</th>
                                    <th class="py-2 px-3 text-right">Nilai Transaksi</th>
                                    <th class="py-2 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($poList as $po)
                                    <tr>
                                        <td class="py-2 px-3 font-mono font-semibold">{{ $po->kode }}</td>
                                        <td class="py-2 px-3">{{ $po->pemasok?->nama ?? '-' }}</td>
                                        <td class="py-2 px-3 font-mono text-zinc-500">{{ $po->tanggal_po ? $po->tanggal_po->format('d/m/Y') : '-' }}</td>
                                        <td class="py-2 px-3 font-mono text-right text-emerald-600 font-semibold">
                                            Rp {{ number_format($po->total_biaya ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <flux:badge size="xs" color="emerald">{{ $po->status }}</flux:badge>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-zinc-500 text-xs">
                                            Belum ada pesanan pembelian PO yang membebani pos anggaran ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button type="button" variant="primary" size="sm" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
