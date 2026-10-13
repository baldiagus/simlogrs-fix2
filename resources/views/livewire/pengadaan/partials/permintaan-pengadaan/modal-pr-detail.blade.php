    {{-- Modal Detail PR --}}
    <flux:modal name="modal-pr-detail" wire:model="showDetailModal" class="md:w-[42rem]">
        @if ($selectedPr)
            <div class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Detail Permintaan Pengadaan</flux:heading>
                    <flux:subheading>{{ $selectedPr->kode }}</flux:subheading>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Pemohon (Unit / Gudang)</div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5 mt-0.5">
                            @if ($selectedPr->unit_id)
                                <span class="text-[10px] px-1.5 py-0.2 rounded font-mono font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Unit</span>
                            @elseif ($selectedPr->gudang_id)
                                <span class="text-[10px] px-1.5 py-0.2 rounded font-mono font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">Gudang</span>
                            @endif
                            <span>{{ $selectedPr->nama_pemohon }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Pengaju</div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPr->pengaju?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Tanggal</div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPr->tanggal_permintaan?->format('d/m/Y') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Urgensi</div>
                        <div><x-ui.urgency-badge :urgency="$selectedPr->urgensi" /></div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Status</div>
                        <div><x-ui.status-badge :status="$selectedPr->status" /></div>
                    </div>
                    @if ($selectedPr->penyetuju)
                        <div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Disetujui/Ditolak Oleh</div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selectedPr->penyetuju->nama }}</div>
                        </div>
                    @endif
                </div>

                @if ($selectedPr->catatan)
                    <div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Catatan</div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800/50 p-2.5 rounded-lg">{{ $selectedPr->catatan }}</div>
                    </div>
                @endif

                {{-- Tabel Detail Item --}}
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                            <tr>
                                <th class="text-left px-3 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">#</th>
                                <th class="text-left px-3 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">Barang</th>
                                <th class="text-right px-3 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">Diminta</th>
                                <th class="text-right px-3 py-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">Disetujui</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($selectedPr->detail as $detail)
                                <tr>
                                    <td class="px-3 py-2 text-zinc-500">{{ $loop->iteration }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->barang?->nama ?? '-' }}</div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 font-mono">{{ $detail->barang?->kode ?? '' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ number_format($detail->jumlah, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums">
                                        @if ($detail->jumlah_disetujui !== null)
                                            {{ number_format($detail->jumlah_disetujui, 0, ',', '.') }}
                                        @else
                                            <span class="text-zinc-500 dark:text-zinc-400 italic text-xs font-sans">Belum diproses</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        @endif
    </flux:modal>
