    {{-- TAB 2: Screening Suku Cadang & Pemeliharaan IPSRS --}}
    @if ($activeTab === 'sparepart')
        <div class="space-y-6">
            {{-- Banner Total Suku Cadang IPSRS --}}
            @if (isset($ringkasan['total_realisasi_sparepart']))
                @php
                    $realisasiSp = (float) $ringkasan['total_realisasi_sparepart'];
                @endphp
                <flux:card class="p-5 border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/40 shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-mono font-bold text-indigo-600 dark:text-indigo-400 uppercase">
                                Screening Pemeliharaan Sarana, TA {{ $filterTahun }}
                            </div>
                            <flux:heading size="lg" class="mt-0.5">Total Pengeluaran Suku Cadang IPSRS</flux:heading>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                Rekapitulasi biaya suku cadang yang dipasang teknisi IPSRS pada lembar kerja SPK perbaikan sarana rumah sakit.
                            </p>
                        </div>
                        <div class="text-right sm:border-l sm:border-zinc-200 dark:sm:border-zinc-700 sm:pl-6">
                            <span class="text-xs text-zinc-600 dark:text-zinc-400">Total Biaya Suku Cadang Terpakai</span>
                            <div class="text-2xl font-bold font-mono text-indigo-600 dark:text-indigo-400 tabular-nums">
                                Rp {{ number_format($realisasiSp, 0, ',', '.') }}
                            </div>
                            <span class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">Status: Akumulasi Real-Time</span>
                        </div>
                    </div>
                </flux:card>
            @endif

            {{-- Navigasi Sub-Tab Suku Cadang: Top Aset vs Log Konsumsi --}}
            <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <button
                    type="button"
                    wire:click="$set('sparepartSubTab', 'top_aset')"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-colors flex items-center gap-2 {{ $sparepartSubTab === 'top_aset' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-xs' : 'bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 border border-zinc-200 dark:border-zinc-800' }}"
                >
                    <flux:icon name="chart-bar" class="size-4" />
                    <span>Top Aset Penyerap Biaya</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-mono {{ $sparepartSubTab === 'top_aset' ? 'bg-zinc-800 text-zinc-200 dark:bg-zinc-200 dark:text-zinc-800' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">{{ $topAset->count() }}</span>
                </button>

                <button
                    type="button"
                    wire:click="$set('sparepartSubTab', 'log_konsumsi')"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-colors flex items-center gap-2 {{ $sparepartSubTab === 'log_konsumsi' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-xs' : 'bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 border border-zinc-200 dark:border-zinc-800' }}"
                >
                    <flux:icon name="clock" class="size-4" />
                    <span>Log Konsumsi Suku Cadang Terakhir</span>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-mono {{ $sparepartSubTab === 'log_konsumsi' ? 'bg-zinc-800 text-zinc-200 dark:bg-zinc-200 dark:text-zinc-800' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400' }}">{{ $riwayatSparepartTerbaru->count() }}</span>
                </button>
            </div>

            {{-- Tampilan Card Tunggal Berdasarkan Sub-Tab --}}
            @if ($sparepartSubTab === 'top_aset')
                <flux:card class="space-y-4 shadow-sm border border-zinc-200 dark:border-zinc-800">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <flux:heading size="base" class="font-bold">Top Aset Penyerap Biaya Pemeliharaan</flux:heading>
                            <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400">
                                Aset sarana rumah sakit dengan konsumsi suku cadang dan biaya servis tertinggi pada TA {{ $filterTahun }}.
                            </flux:subheading>
                        </div>
                        <flux:badge size="sm" color="indigo">{{ $topAset->count() }} Sarana Terbanyak</flux:badge>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2.5 px-3.5 font-semibold whitespace-nowrap">Nama Sarana Aset</th>
                                    <th class="py-2.5 px-3.5 font-semibold whitespace-nowrap">Kode Register</th>
                                    <th class="py-2.5 px-3.5 font-semibold whitespace-nowrap">Lokasi Ruangan</th>
                                    <th class="py-2.5 px-3.5 font-semibold text-center whitespace-nowrap">Frekuensi Servis</th>
                                    <th class="py-2.5 px-3.5 font-semibold text-right whitespace-nowrap">Biaya Sparepart (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($topAset as $aset)
                                    <tr>
                                        <td class="py-3 px-3.5 font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $aset->nama ?? $aset->barang?->nama ?? '-' }}
                                        </td>
                                        <td class="py-3 px-3.5 font-mono text-[11px] text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                            {{ $aset->kode_aset ?? $aset->kode }}
                                        </td>
                                        <td class="py-3 px-3.5 text-zinc-600 dark:text-zinc-400">
                                            {{ $aset->unit?->nama ?? 'Sentral' }}
                                        </td>
                                        <td class="py-3 px-3.5 text-center font-mono font-medium whitespace-nowrap">
                                            {{ $aset->frekuensi_servis }}x
                                        </td>
                                        <td class="py-3 px-3.5 font-mono text-right text-indigo-600 dark:text-indigo-400 font-bold tabular-nums whitespace-nowrap">
                                            Rp {{ number_format($aset->total_biaya_sparepart ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada data pemakaian suku cadang pada aset untuk TA {{ $filterTahun }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            @else
                <flux:card class="space-y-4 shadow-sm border border-zinc-200 dark:border-zinc-800">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <flux:heading size="base" class="font-bold">Log Konsumsi Suku Cadang Terakhir</flux:heading>
                            <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400">
                                Catatan suku cadang yang dipasang teknisi pada lembar kerja tiket servis SPK.
                            </flux:subheading>
                        </div>
                        <flux:badge size="sm" color="emerald">{{ $riwayatSparepartTerbaru->count() }} Log Terkini</flux:badge>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2.5 px-3.5 font-semibold whitespace-nowrap">Tiket SPK</th>
                                    <th class="py-2.5 px-3.5 font-semibold whitespace-nowrap">Aset / Sarana Terkait</th>
                                    <th class="py-2.5 px-3.5 font-semibold whitespace-nowrap">Nama Sparepart</th>
                                    <th class="py-2.5 px-3.5 font-semibold whitespace-nowrap">Nomor Batch</th>
                                    <th class="py-2.5 px-3.5 font-semibold text-center whitespace-nowrap">Jumlah Digunakan</th>
                                    <th class="py-2.5 px-3.5 font-semibold text-right whitespace-nowrap">Subtotal (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($riwayatSparepartTerbaru as $spLog)
                                    <tr>
                                        <td class="py-3 px-3.5 font-mono font-semibold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                            {{ $spLog->permintaanMaintenance?->kode ?? '-' }}
                                        </td>
                                        <td class="py-3 px-3.5 text-zinc-800 dark:text-zinc-200">
                                            {{ $spLog->permintaanMaintenance?->aset?->nama ?? $spLog->permintaanMaintenance?->aset?->barang?->nama ?? '-' }}
                                        </td>
                                        <td class="py-3 px-3.5 font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $spLog->barang?->nama ?? '-' }}
                                        </td>
                                        <td class="py-3 px-3.5 font-mono text-[11px] text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                            {{ $spLog->stokBatch?->nomor_batch ?? '-' }}
                                        </td>
                                        <td class="py-3 px-3.5 text-center font-mono font-medium whitespace-nowrap">
                                            {{ $spLog->jumlah_digunakan }}
                                        </td>
                                        <td class="py-3 px-3.5 font-mono text-right text-emerald-600 dark:text-emerald-400 font-semibold tabular-nums whitespace-nowrap">
                                            Rp {{ number_format($spLog->subtotal ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada log pergantian suku cadang yang tercatat untuk TA {{ $filterTahun }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            @endif
        </div>
    @endif
