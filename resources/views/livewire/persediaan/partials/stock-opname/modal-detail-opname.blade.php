    {{-- Modal Detail Rekonsiliasi Opname --}}
    <flux:modal name="modal-detail-opname" wire:model="showDetailModal" class="w-full max-w-[95vw] lg:max-w-6xl">
        @if ($selectedOpname)
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Rekonsiliasi: {{ $selectedOpname->kode }}</flux:heading>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Gudang: {{ $selectedOpname->gudang?->nama }} &bull; Tanggal: {{ $selectedOpname->tanggal_opname?->format('d F Y') }}
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <x-ui.status-badge :status="$selectedOpname->status" />
                    </div>
                </div>

                {{-- Tabel Hasil Rekonsiliasi Lengkap --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-xl max-h-[55vh] overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700 sticky top-0">
                            <tr>
                                <th class="p-2.5 w-10 text-center">#</th>
                                <th class="p-2.5">Komoditas Barang</th>
                                <th class="p-2.5">Nomor Batch</th>
                                <th class="p-2.5">Lokasi Rak</th>
                                <th class="p-2.5 text-right w-28">Saldo Sistem</th>
                                <th class="p-2.5 text-right w-28">Hitung Fisik</th>
                                <th class="p-2.5 text-right w-28">Selisih</th>
                                <th class="p-2.5">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach ($selectedOpname->detail as $det)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40">
                                    <td class="p-2.5 text-center text-zinc-400 font-mono">{{ $loop->iteration }}</td>
                                    <td class="p-2.5">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $det->barang?->nama }}</div>
                                        <div class="text-[10px] text-zinc-500 font-mono">{{ $det->barang?->kode }}</div>
                                    </td>
                                    <td class="p-2.5 font-mono text-zinc-800 dark:text-zinc-200">
                                        {{ $det->stokBatch?->nomor_batch ?? '-' }}
                                    </td>
                                    <td class="p-2.5 text-zinc-600 dark:text-zinc-400">
                                        {{ $det->stokBatch?->lokasiGudang?->nama ?? 'Tanpa Rak' }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono text-zinc-700 dark:text-zinc-300">
                                        {{ number_format($det->jumlah_sistem, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($det->jumlah_fisik, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-bold {{ $det->selisih < 0 ? 'text-rose-600 dark:text-rose-400' : ($det->selisih > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500') }}">
                                        {{ $det->selisih > 0 ? '+' : '' }}{{ number_format($det->selisih, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-zinc-600 dark:text-zinc-400">
                                        {{ $det->catatan ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        @endif
    </flux:modal>
