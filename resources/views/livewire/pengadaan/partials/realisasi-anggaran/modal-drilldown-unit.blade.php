    {{-- Modal Drilldown Pengeluaran Unit (SPPB Distribusi & Pemeliharaan Sparepart) --}}
    <flux:modal name="modal-drilldown-unit" wire:model="showDetailModal" class="md:w-[48rem]">
        @if ($selectedUnit || $selectedAnggaran)
            @php
                $activeUnit = $selectedUnit ?? $selectedAnggaran;
                $totMod = (float) ($selectedBebanDistribusi + $selectedBebanSparepart);
            @endphp
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="text-[11px] font-mono text-blue-600 dark:text-blue-400 font-bold uppercase">
                            Screening Pengeluaran, TA {{ $filterTahun }}
                        </div>
                        <flux:heading size="lg" class="mt-0.5">
                            {{ $activeUnit->nama ?? $activeUnit->unit?->nama ?? $activeUnit->pos_anggaran }}
                        </flux:heading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <flux:badge size="sm" color="blue">
                            Unit Kerja ({{ $activeUnit->kode }})
                        </flux:badge>
                    </div>
                </div>

                {{-- Ringkasan 3 Stat Box Modal --}}
                <div class="grid grid-cols-3 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 text-xs">
                    <div>
                        <span class="text-zinc-600 dark:text-zinc-400 text-[11px]">Pengeluaran Amprah:</span>
                        <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400 text-sm mt-0.5 tabular-nums">
                            Rp {{ number_format($selectedBebanDistribusi, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-600 dark:text-zinc-400 text-[11px]">Konsumsi Sparepart:</span>
                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400 text-sm mt-0.5 tabular-nums">
                            Rp {{ number_format($selectedBebanSparepart, 0, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <span class="text-zinc-600 dark:text-zinc-400 text-[11px]">Total Pengeluaran Unit:</span>
                        <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100 text-sm mt-0.5 tabular-nums">
                            Rp {{ number_format($totMod, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                {{-- Bagian 1: Riwayat Pengiriman Distribusi Barang (SPPB) --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Bukti Surat Jalan Distribusi (SPPB) yang Diterima Ruangan</flux:heading>
                        <flux:badge size="xs" color="emerald">{{ $riwayatSppbUnit->count() }} Dokumen</flux:badge>
                    </div>

                    <div class="overflow-x-auto max-h-48 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2 px-3">Nomor SPPB</th>
                                    <th class="py-2 px-3">Tanggal Kirim</th>
                                    <th class="py-2 px-3">Asal Gudang</th>
                                    <th class="py-2 px-3">Daftar Komoditas</th>
                                    <th class="py-2 px-3 text-right">Nilai Barang</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($riwayatSppbUnit as $sppb)
                                    @php
                                        $nilaiSppb = $sppb->detail->sum(function ($d) {
                                            return $d->jumlah_dikirim * ($d->stokBatch?->harga_satuan ?? 0);
                                        });
                                    @endphp
                                    <tr>
                                        <td class="py-2 px-3 font-mono font-semibold">{{ $sppb->kode }}</td>
                                        <td class="py-2 px-3 font-mono text-zinc-500">{{ $sppb->tanggal_kirim ? $sppb->tanggal_kirim->format('d/m/Y') : '-' }}</td>
                                        <td class="py-2 px-3 text-zinc-700 dark:text-zinc-300">{{ $sppb->gudang?->nama ?? '-' }}</td>
                                        <td class="py-2 px-3 text-zinc-600 dark:text-zinc-400">
                                            @foreach ($sppb->detail as $itemDetail)
                                                <div class="truncate max-w-xs">&bull; {{ $itemDetail->barang?->nama }} ({{ $itemDetail->jumlah_dikirim }})</div>
                                            @endforeach
                                        </td>
                                        <td class="py-2 px-3 font-mono text-right text-emerald-600 dark:text-emerald-400 font-semibold tabular-nums">
                                            Rp {{ number_format($nilaiSppb, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada pengiriman distribusi barang yang dibebankan ke unit ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Bagian 2: Riwayat Konsumsi Sparepart Aset Ruangan --}}
                <div class="space-y-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">Suku Cadang yang Dikonsumsi untuk Servis Aset Ruangan Ini</flux:heading>
                        <flux:badge size="xs" color="indigo">{{ $riwayatServisUnit->count() }} Komponen</flux:badge>
                    </div>

                    <div class="overflow-x-auto max-h-48 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="py-2 px-3">Tiket Servis</th>
                                    <th class="py-2 px-3">Sarana / Aset</th>
                                    <th class="py-2 px-3">Nama Sparepart</th>
                                    <th class="py-2 px-3 text-center">Jumlah</th>
                                    <th class="py-2 px-3 text-right">Subtotal (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($riwayatServisUnit as $servis)
                                    <tr>
                                        <td class="py-2 px-3 font-mono font-semibold">{{ $servis->permintaanMaintenance?->kode ?? '-' }}</td>
                                        <td class="py-2 px-3 text-zinc-800 dark:text-zinc-200">{{ $servis->permintaanMaintenance?->aset?->nama ?? '-' }}</td>
                                        <td class="py-2 px-3 text-zinc-700 dark:text-zinc-300">{{ $servis->barang?->nama ?? '-' }}</td>
                                        <td class="py-2 px-3 text-center font-mono">{{ $servis->jumlah_digunakan }}</td>
                                        <td class="py-2 px-3 font-mono text-right text-indigo-600 dark:text-indigo-400 font-semibold tabular-nums">
                                            Rp {{ number_format($servis->subtotal ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                            Belum ada suku cadang pemeliharaan yang dibebankan pada aset ruangan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @endif
    </flux:modal>
