    {{-- Modal Review & Kuota Distribusi --}}
    <flux:modal name="modal-review-kuota" wire:model="showReviewModal" class="md:w-4xl max-w-5xl">
        @if ($selectedPermintaan)
            <div class="space-y-5">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Review & Penyesuaian Kuota Distribusi</flux:heading>
                    <flux:subheading class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        Periksa ketersediaan stok fisik gudang dan sesuaikan kuota barang yang disetujui untuk didistribusikan.
                    </flux:subheading>
                </div>

                {{-- Header Ringkasan Permintaan --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Kode Amprah</span>
                        <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->kode }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Unit Pemohon</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->unit?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Gudang Penyedia</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPermintaan->gudang?->nama ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400 block">Tingkat Urgensi</span>
                        <div class="mt-0.5">
                            <x-urgency-badge :urgency="$selectedPermintaan->urgensi" />
                        </div>
                    </div>
                </div>

                @if ($selectedPermintaan->catatan)
                    <div class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 rounded-lg text-xs">
                        <span class="font-semibold text-amber-800 dark:text-amber-300">Catatan Pemohon:</span>
                        <span class="text-amber-900 dark:text-amber-200 ml-1">{{ $selectedPermintaan->catatan }}</span>
                    </div>
                @endif

                {{-- Tabel Penyesuaian Kuota --}}
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 font-semibold">
                                <th class="py-2.5 px-3">Nama Barang</th>
                                <th class="py-2.5 px-3 text-center">Satuan</th>
                                <th class="py-2.5 px-3 text-right">Diminta Ruangan</th>
                                <th class="py-2.5 px-3 text-right">Stok Fisik Bebas</th>
                                <th class="py-2.5 px-3 text-right">Disetujui Penuh</th>
                                <th class="py-2.5 px-3 text-right">Rencana Penyaluran</th>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($itemQtys as $detailId => $item)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item['barang_nama'] }}</div>
                                        @if (!empty($item['barang_kode']))
                                            <div class="text-[11px] font-mono text-zinc-500">{{ $item['barang_kode'] }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-center text-zinc-600 dark:text-zinc-400">
                                        {{ $item['satuan'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ number_format($item['jumlah_diminta'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono">
                                        @if ($item['stok_gudang'] <= 0)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                                                0 (Kosong)
                                            </span>
                                        @elseif ($item['stok_gudang'] < $item['jumlah_diminta'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                                {{ number_format($item['stok_gudang'], 0, ',', '.') }} (Kurang)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                {{ number_format($item['stok_gudang'], 0, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($item['jumlah_disetujui'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        @php
                                            $readyQty = min($item['stok_gudang'], $item['jumlah_diminta']);
                                            $backorderQty = max(0, $item['jumlah_diminta'] - $item['stok_gudang']);
                                        @endphp
                                        @if ($backorderQty > 0)
                                            <div class="flex flex-col items-end gap-0.5">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    Kirim: {{ $readyQty }}
                                                </span>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                    Hutang: {{ $backorderQty }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                Kirim 100% (Lengkap)
                                            </span>
                                        @endif
                                    </td>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 italic">
                        * Permintaan ruangan disetujui penuh 100% tanpa pemotongan. Sisa barang yang belum ada stoknya otomatis menjadi hutang gudang dan diterbitkan SPPB susulan saat restok.
                    </div>
                    <div>
                        <flux:button type="button" variant="primary" wire:click="confirmApproveGudang" class="bg-blue-600 hover:bg-blue-700 text-white">
                            Setujui Distribusi
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
