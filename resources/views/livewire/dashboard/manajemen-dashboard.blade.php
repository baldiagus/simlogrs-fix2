<div class="space-y-6">
    {{-- Header & Quick Navigation Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Pusat Kendali Eksekutif & Manajemen Direksi</flux:heading>
            <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Ringkasan strategis makro: valuasi persediaan, serapan pagu belanja, kelaikan fasilitas sarana IPSRS, dan kepatuhan audit.
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button href="/manajemen/realisasi-anggaran" wire:navigate variant="outline" size="sm" icon="banknotes">
                Pagu Anggaran
            </flux:button>
            <flux:button href="/manajemen/master-rekanan" wire:navigate variant="outline" size="sm" icon="building-storefront">
                Evaluasi Rekanan
            </flux:button>
            <flux:button href="/manajemen/audit-trail" wire:navigate variant="outline" size="sm" icon="shield-check">
                Audit Trail
            </flux:button>
        </div>
    </div>

    {{-- 4 Kartu KPI Makro Eksekutif --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- KPI 1: Valuasi Persediaan Fisik --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Valuasi Persediaan</span>
                <flux:icon name="cube" class="size-4 text-blue-600" />
            </div>
            <div class="text-xl font-bold font-mono text-zinc-900 dark:text-zinc-100">
                Rp {{ number_format($valuasiPersediaan, 0, ',', '.') }}
            </div>
            <div class="text-[11px] text-zinc-500">Nilai aset barang aktif di rak gudang</div>
        </flux:card>

        {{-- KPI 2: Serapan Pagu Belanja --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Serapan Pagu TA 2026</span>
                <span class="text-xs font-bold font-mono {{ $serapanPersen > 75 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $serapanPersen }}%</span>
            </div>
            <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 overflow-hidden">
                <div class="h-2 rounded-full {{ $serapanPersen > 75 ? 'bg-amber-500' : 'bg-emerald-600' }}" style="width: {{ min(100, $serapanPersen) }}%"></div>
            </div>
            <div class="text-[11px] text-zinc-500 flex justify-between font-mono">
                <span>Beban: Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</span>
                <span>Pagu: Rp {{ number_format($totalPagu, 0, ',', '.') }}</span>
            </div>
            <div class="text-[10px] text-zinc-400 flex justify-between pt-1 border-t border-zinc-100 dark:border-zinc-800">
                <span>Amprah: Rp {{ number_format($totalDistribusi ?? 0, 0, ',', '.') }}</span>
                <span>Sparepart: Rp {{ number_format($totalSparepart ?? 0, 0, ',', '.') }}</span>
            </div>
        </flux:card>

        {{-- KPI 3: Indeks Kelaikan Sarana IPSRS --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Kelaikan Sarana (IPSRS)</span>
                <span class="text-xs font-bold font-mono text-indigo-600 dark:text-indigo-400">{{ $indeksKelaikan }}%</span>
            </div>
            <div class="text-xl font-bold font-mono text-indigo-600 dark:text-indigo-400">
                {{ $asetBaik }} / {{ $totalAset }} Aset Baik
            </div>
            <div class="text-[11px] text-zinc-500">Kondisi fisik sarana rumah sakit</div>
        </flux:card>

        {{-- KPI 4: Tata Kelola & Closing Rate Audit --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 shadow-xs">
            <div class="flex items-center justify-between text-zinc-500 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Closing Rate CAPA</span>
                <span class="text-xs font-bold font-mono text-emerald-600">{{ $closingRate }}%</span>
            </div>
            <div class="text-xl font-bold font-mono text-zinc-900 dark:text-zinc-100">
                {{ $closedTemuan }} / {{ $totalTemuan }} Kasus Selesai
            </div>
            <div class="text-[11px] text-zinc-500">Tingkat kepatuhan tindak lanjut SPI</div>
        </flux:card>
    </div>

    {{-- Early Warning Panels (Papan Peringatan Dini) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Panel 1: Batch Persediaan Menjelang Kedaluwarsa --}}
        <flux:card class="space-y-3 border-t-4 border-t-amber-500 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="base" class="font-bold">Peringatan Kedaluwarsa Kritis (&le; 90 Hari)</flux:heading>
                    <flux:subheading class="text-xs text-zinc-500">Potensi kerugian fisik persediaan di rak gudang.</flux:subheading>
                </div>
                <flux:badge size="sm" :color="$batchKritis->count() > 0 ? 'amber' : 'emerald'">
                    {{ $batchKritis->count() }} Batch
                </flux:badge>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="py-2 px-2.5">Barang</th>
                            <th class="py-2 px-2.5">Batch</th>
                            <th class="py-2 px-2.5 text-center">Kedaluwarsa</th>
                            <th class="py-2 px-2.5 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($batchKritis as $b)
                            <tr>
                                <td class="py-2 px-2.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $b->barang?->nama ?? '-' }}</td>
                                <td class="py-2 px-2.5 font-mono text-zinc-600 dark:text-zinc-400">{{ $b->nomor_batch }}</td>
                                <td class="py-2 px-2.5 text-center font-mono text-rose-600 font-semibold">
                                    {{ $b->tanggal_kedaluwarsa ? $b->tanggal_kedaluwarsa->format('d/m/Y') : '-' }}
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono font-semibold">{{ $b->jumlah }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-5 text-center text-zinc-500 text-xs">
                                    Tidak ada persediaan yang mendekati batas kedaluwarsa kritis.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-2 text-right">
                <a href="/manajemen/monitoring-fefo" wire:navigate class="text-xs text-blue-600 hover:underline font-semibold inline-flex items-center gap-1">
                    <span>Buka Monitoring FEFO Lengkap</span>
                    <flux:icon name="arrow-right" class="size-3" />
                </a>
            </div>
        </flux:card>

        {{-- Panel 2: SPK Pemeliharaan IPSRS Prioritas CITO / Tinggi --}}
        <flux:card class="space-y-3 border-t-4 border-t-rose-500 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="base" class="font-bold">Servis IPSRS Mendesak (CITO / Tinggi)</flux:heading>
                    <flux:subheading class="text-xs text-zinc-500">Sarana ruangan yang mengalami kerusakan dan butuh penanganan.</flux:subheading>
                </div>
                <flux:badge size="sm" :color="$spkMendesak->count() > 0 ? 'rose' : 'emerald'">
                    {{ $spkMendesak->count() }} Kasus
                </flux:badge>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="py-2 px-2.5">Tiket</th>
                            <th class="py-2 px-2.5">Nama Sarana Aset</th>
                            <th class="py-2 px-2.5">Ruangan</th>
                            <th class="py-2 px-2.5 text-center">Prioritas</th>
                            <th class="py-2 px-2.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($spkMendesak as $spk)
                            <tr>
                                <td class="py-2 px-2.5 font-mono font-semibold">{{ $spk->kode }}</td>
                                <td class="py-2 px-2.5 font-medium text-zinc-900 dark:text-zinc-100">{{ $spk->aset?->nama ?? '-' }}</td>
                                <td class="py-2 px-2.5 text-zinc-600 dark:text-zinc-400">{{ $spk->unit?->nama ?? '-' }}</td>
                                <td class="py-2 px-2.5 text-center">
                                    <flux:badge size="xs" :color="$spk->prioritas === 'CITO' ? 'rose' : 'amber'">{{ $spk->prioritas }}</flux:badge>
                                </td>
                                <td class="py-2 px-2.5 text-center">
                                    <flux:badge size="xs" color="blue">{{ $spk->status }}</flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-5 text-center text-zinc-500 text-xs">
                                    Tidak ada tiket servis prioritas CITO yang pending. Seluruh sarana normal.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-2 text-right">
                <a href="/manajemen/spk" wire:navigate class="text-xs text-blue-600 hover:underline font-semibold inline-flex items-center gap-1">
                    <span>Buka Daftar SPK IPSRS</span>
                    <flux:icon name="arrow-right" class="size-3" />
                </a>
            </div>
        </flux:card>
    </div>
</div>
