    {{-- Modal Detail Alokasi Batch FEFO --}}
    <flux:modal wire:model="showDetailModal" class="w-full max-w-[92vw] lg:max-w-5xl">
        @if ($selectedSppb)
            <div class="space-y-4">
                <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div>
                        <flux:heading size="lg">Detail Alokasi SPPB: {{ $selectedSppb->kode }}</flux:heading>
                        <flux:subheading>
                            Ref Amprah: <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->permintaanDistribusi?->kode ?? '-' }}</span> &bull;
                            Unit Tujuan: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->unit?->nama ?? '-' }}</span>
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-ui.status-badge :status="$selectedSppb->status" />
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg">
                    <div>
                        <span class="text-zinc-500">Gudang Pengirim:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Tanggal Pengiriman:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->tanggal_kirim?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Petugas Picking:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedSppb->pengirim?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <span class="text-zinc-500">Total Kuantitas:</span>
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($selectedSppb->detail->sum('jumlah_dikirim'), 0, ',', '.') }} unit</div>
                    </div>
                </div>

                @if ($selectedSppb->catatan)
                    <div class="text-xs text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800/80 p-2.5 rounded-md">
                        <span class="font-semibold">Catatan:</span> {{ $selectedSppb->catatan }}
                    </div>
                @endif

                {{-- Tabel Rincian Batch FEFO --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-xs">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-600 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700 text-left font-semibold">
                            <tr>
                                <th class="py-2.5 px-3 w-10 text-center">No</th>
                                <th class="py-2.5 px-3">Nama Komoditas</th>
                                <th class="py-2.5 px-3">Nomor Batch</th>
                                <th class="py-2.5 px-3">Kedaluwarsa (FEFO)</th>
                                <th class="py-2.5 px-3">Lokasi Rak / Bin</th>
                                <th class="py-2.5 px-3 text-right">Kuantitas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($selectedSppb->detail as $idx => $det)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2 px-3 text-center text-zinc-500 dark:text-zinc-400">{{ $idx + 1 }}</td>
                                    <td class="py-2 px-3 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $det->barang?->nama ?? '-' }}
                                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $det->barang?->kode ?? '' }}</div>
                                    </td>
                                    <td class="py-2 px-3 font-mono font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ $det->stokBatch?->nomor_batch ?? '-' }}
                                    </td>
                                    <td class="py-2 px-3">
                                        @if ($det->stokBatch?->tanggal_kedaluwarsa)
                                            <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 font-medium">
                                                {{ $det->stokBatch->tanggal_kedaluwarsa->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="text-zinc-500 dark:text-zinc-400 italic">Non-Expired</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3">
                                        @if ($det->stokBatch?->lokasiGudang)
                                            <span class="inline-flex items-center gap-1 font-mono text-xs">
                                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $det->stokBatch->lokasiGudang->nama }}</span>
                                                <span class="text-zinc-500 dark:text-zinc-400">({{ $det->stokBatch->lokasiGudang->kode }})</span>
                                            </span>
                                        @else
                                            <span class="text-zinc-500 dark:text-zinc-400">-</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($det->jumlah_dikirim, 0, ',', '.') }}
                                        <span class="text-xs font-normal text-zinc-500 font-sans">{{ $det->barang?->satuan?->simbol ?? $det->barang?->satuan?->nama ?? '' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-zinc-500 dark:text-zinc-400">Tidak ada detail alokasi batch.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        @endif
    </flux:modal>
