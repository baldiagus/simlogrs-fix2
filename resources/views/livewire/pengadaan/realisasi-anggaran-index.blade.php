<div class="space-y-6">
    {{-- Header & Selector Tahun Anggaran --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-bold tracking-tight">Screening Pengeluaran Logistik Ruangan & Suku Cadang IPSRS</flux:heading>
            <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                Monitoring pengeluaran fisik riil: distribusi amprah per unit dan konsumsi suku cadang pemeliharaan aset.
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <label for="filter-tahun" class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Tahun Anggaran:</label>
            <select
                id="filter-tahun"
                wire:model.live="filterTahun"
                class="h-9 text-xs font-semibold rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
                <option value="2026">TA 2026 (Tahun Berjalan)</option>
                <option value="2025">TA 2025 (Arsip)</option>
            </select>
        </div>
    </div>

    {{-- Navigasi Sub-Tab Hub Evaluasi --}}
    <x-tabs.pengadaan-evaluasi active="anggaran" />

    {{-- 2 Kartu Screening Pengeluaran Riil (Tanpa Stripe Dekoratif - Anti Slop R-01 & R-31) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {{-- Pengeluaran Amprah Unit --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Pengeluaran Amprah Unit</span>
                <flux:icon name="truck" class="size-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                Rp {{ number_format($ringkasan['total_realisasi_distribusi'], 0, ',', '.') }}
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Akumulasi nilai komoditas logistik yang telah didistribusikan dari gudang ke unit ruangan pada TA {{ $filterTahun }}.
            </p>
        </flux:card>

        {{-- Konsumsi Sparepart IPSRS --}}
        <flux:card class="space-y-2 p-5 border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs">
            <div class="flex items-center justify-between text-zinc-600 dark:text-zinc-400">
                <span class="text-xs font-semibold uppercase tracking-wider">Total Konsumsi Sparepart IPSRS</span>
                <flux:icon name="wrench" class="size-5 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div class="text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400 tabular-nums">
                Rp {{ number_format($ringkasan['total_realisasi_sparepart'], 0, ',', '.') }}
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Akumulasi nilai suku cadang terpasang pada servis perbaikan sarana rumah sakit pada TA {{ $filterTahun }}.
            </p>
        </flux:card>
    </div>

    {{-- Tab Navigasi Sudut Pandang --}}
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-6">
        <button
            type="button"
            wire:click="$set('activeTab', 'unit')"
            class="pb-3 text-xs font-semibold transition-colors border-b-2 flex items-center gap-2 {{ $activeTab === 'unit' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
        >
            <flux:icon name="building-office-2" class="size-4" />
            <span>Screening Pengeluaran Tiap Unit Ruangan</span>
        </button>

        <button
            type="button"
            wire:click="$set('activeTab', 'sparepart')"
            class="pb-3 text-xs font-semibold transition-colors border-b-2 flex items-center gap-2 {{ $activeTab === 'sparepart' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}"
        >
            <flux:icon name="wrench-screwdriver" class="size-4" />
            <span>Konsumsi Suku Cadang & Pemeliharaan IPSRS</span>
        </button>
    </div>

    {{-- TAB 1: Screening Pengeluaran Tiap Unit Ruangan --}}
    @if ($activeTab === 'unit')
        <flux:card class="space-y-4 shadow-sm border border-zinc-200 dark:border-zinc-800">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <flux:heading size="lg">Screening Pengeluaran Tiap Unit Ruangan</flux:heading>
                    <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400">
                        Screening nilai barang logistik yang diterima unit (amprah) dan suku cadang servis sarana di unit tersebut.
                    </flux:subheading>
                </div>
                <div class="w-full sm:w-64">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari unit ruangan..."
                        class="w-full h-9 text-xs rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="py-2.5 px-3 font-semibold">Kode</th>
                            <th class="py-2.5 px-3 font-semibold">Unit Kerja / Ruangan</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Pengeluaran Amprah (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Konsumsi Sparepart (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Total Pengeluaran Unit (Rp)</th>
                            <th class="py-2.5 px-3 font-semibold text-center">Rincian Drilldown</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($unitAnggarans as $anggaran)
                            @php
                                $distribusi = (float) $anggaran->beban_distribusi;
                                $sparepart = (float) $anggaran->beban_sparepart;
                                $totalBeban = (float) $anggaran->realisasi;
                            @endphp
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40" wire:key="unit-row-{{ $anggaran->id }}">
                                <td class="py-2.5 px-3 font-mono font-semibold text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                                    {{ $anggaran->kode }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $anggaran->nama ?? $anggaran->unit?->nama ?? $anggaran->pos_anggaran }}
                                    </div>
                                    <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">Kode: {{ $anggaran->kode }}</div>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right text-emerald-600 dark:text-emerald-400 font-semibold tabular-nums">
                                    Rp {{ number_format($distribusi, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right text-indigo-600 dark:text-indigo-400 font-semibold tabular-nums">
                                    Rp {{ number_format($sparepart, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 font-mono text-right font-bold text-zinc-900 dark:text-zinc-100 tabular-nums">
                                    Rp {{ number_format($totalBeban, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <flux:button
                                        wire:click="openDetail({{ $anggaran->id }})"
                                        size="xs"
                                        variant="ghost"
                                        icon="eye"
                                        title="Buka Rincian Bukti Fisik"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                    Tidak ada data unit kerja yang sesuai dengan kata kunci pencarian pada TA {{ $filterTahun }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif

    @include('livewire.pengadaan.partials.realisasi-anggaran.tab-sparepart')

    @include('livewire.pengadaan.partials.realisasi-anggaran.modal-drilldown-unit')
</div>
