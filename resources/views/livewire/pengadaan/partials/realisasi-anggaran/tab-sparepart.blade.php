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

            {{-- 2 Grid: Top Aset Penyerap Biaya & Riwayat Log Sparepart --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                {{-- Panel Kiri: Top 5 Aset Penyerap Biaya Pemeliharaan --}}
                <flux:card class="space-y-3 shadow-sm border border-zinc-200 dark:border-zinc-800">
                    <div>
                        <flux:heading size="base" class="font-bold">Top Aset Penyerap Biaya Pemeliharaan</flux:heading>
                        <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400">
                            Aset sarana rumah sakit dengan konsumsi suku cadang dan biaya servis tertinggi.
                        </flux:subheading>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2 px-3 font-semibold">Nama Sarana Aset</th>
                                    <th class="py-2 px-3 font-semibold">Lokasi Ruangan</th>
                                    <th class="py-2 px-3 font-semibold text-center">Servis</th>
                                    <th class="py-2 px-3 font-semibold text-right">Biaya Sparepart (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($topAset as $aset)
                                    <tr>
                                        <td class="py-2.5 px-3">
                                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $aset->nama }}</div>
                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $aset->kode_aset ?? $aset->kode }}</div>
                                        </td>
                                        <td class="py-2.5 px-3 text-zinc-600 dark:text-zinc-400">
                                            {{ $aset->unit?->nama ?? 'Sentral' }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-mono font-medium">
                                            {{ $aset->frekuensi_servis }}x
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-right text-indigo-600 dark:text-indigo-400 font-bold tabular-nums">
                                            Rp {{ number_format($aset->total_biaya_sparepart ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada data pemakaian suku cadang pada aset untuk TA {{ $filterTahun }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>

                {{-- Panel Kanan: Riwayat 10 Log Pemakaian Suku Cadang Terakhir --}}
                <flux:card class="space-y-3 shadow-sm border border-zinc-200 dark:border-zinc-800">
                    <div>
                        <flux:heading size="base" class="font-bold">Log Konsumsi Suku Cadang Terakhir</flux:heading>
                        <flux:subheading class="text-xs text-zinc-600 dark:text-zinc-400">
                            Catatan suku cadang yang dipasang teknisi pada tiket servis SPK.
                        </flux:subheading>
                    </div>

                    <div class="overflow-x-auto max-h-80">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                                <tr>
                                    <th class="py-2 px-3 font-semibold">Tiket SPK</th>
                                    <th class="py-2 px-3 font-semibold">Nama Sparepart</th>
                                    <th class="py-2 px-3 font-semibold text-center">Jumlah</th>
                                    <th class="py-2 px-3 font-semibold text-right">Subtotal (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($riwayatSparepartTerbaru as $spLog)
                                    <tr>
                                        <td class="py-2 px-3">
                                            <div class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ $spLog->permintaanMaintenance?->kode ?? '-' }}
                                            </div>
                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400">
                                                {{ $spLog->permintaanMaintenance?->aset?->nama ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-3">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $spLog->barang?->nama ?? '-' }}
                                            </div>
                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">
                                                Batch: {{ $spLog->stokBatch?->nomor_batch ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-3 text-center font-mono font-medium">
                                            {{ $spLog->jumlah_digunakan }}
                                        </td>
                                        <td class="py-2 px-3 font-mono text-right text-emerald-600 dark:text-emerald-400 font-semibold tabular-nums">
                                            Rp {{ number_format($spLog->subtotal ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada log pergantian suku cadang yang tercatat untuk TA {{ $filterTahun }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </flux:card>
            </div>
        </div>
    @endif
