    {{-- Modal Detail Riwayat Pengembalian --}}
    <flux:modal name="modal-detail-pengembalian" wire:model="showDetailPengembalianModal" class="md:w-[54rem] max-w-[95vw]">
        @if ($selectedPengembalian)
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Detail Riwayat Pengembalian Aset</flux:heading>
                            <span class="font-mono text-sm font-bold text-zinc-500 dark:text-zinc-400">#{{ $selectedPengembalian->kode }}</span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Tanggal Kembali: {{ $selectedPengembalian->tanggal_kembali_aktual?->format('d F Y') }}
                        </flux:subheading>
                    </div>
                    <div style="margin-right: 2.25rem;">
                        <flux:badge size="sm" color="emerald" inset="top bottom">SELESAI</flux:badge>
                    </div>
                </div>

                {{-- Metadata --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Ref Peminjaman</div>
                        <div class="font-mono font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPengembalian->permintaanPeminjaman?->kode ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Unit Peminjam</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPengembalian->permintaanPeminjaman?->unitPeminjam?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Diperiksa Oleh</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPengembalian->pemeriksa?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Item Aset</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">{{ $selectedPengembalian->detail->count() }} unit</div>
                    </div>
                </div>

                @if ($selectedPengembalian->catatan)
                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 text-xs">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">Catatan Pengembalian:</span>
                        <p class="text-zinc-600 dark:text-zinc-400 mt-1">{{ $selectedPengembalian->catatan }}</p>
                    </div>
                @endif

                {{-- Tabel Rincian Kondisi Aset --}}
                <div>
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-2">
                        Rincian Hasil Inspeksi Fisik
                    </div>
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-100 dark:bg-zinc-800/80 font-semibold text-zinc-700 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3.5 py-2.5 w-10">#</th>
                                    <th class="px-3.5 py-2.5">Aset</th>
                                    <th class="px-3.5 py-2.5 w-36">Kondisi Akhir</th>
                                    <th class="px-3.5 py-2.5">Catatan Inspeksi</th>
                                    <th class="px-3.5 py-2.5 w-44">Tiket IPSRS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                                @foreach ($selectedPengembalian->detail as $detail)
                                    <tr>
                                        <td class="px-3.5 py-2.5 text-zinc-400 font-mono">{{ $loop->iteration }}</td>
                                        <td class="px-3.5 py-2.5">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->aset?->barang?->nama ?? '-' }}</div>
                                            <div class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">Kode: {{ $detail->aset?->kode_aset ?? '-' }}</div>
                                        </td>
                                        <td class="px-3.5 py-2.5">
                                            @if ($detail->kondisi_saat_kembali === 'BAIK')
                                                <flux:badge size="sm" color="emerald" inset="top bottom">BAIK</flux:badge>
                                            @elseif ($detail->kondisi_saat_kembali === 'RUSAK_RINGAN')
                                                <flux:badge size="sm" color="amber" inset="top bottom">RUSAK RINGAN</flux:badge>
                                            @else
                                                <flux:badge size="sm" color="red" inset="top bottom">RUSAK BERAT</flux:badge>
                                            @endif
                                        </td>
                                        <td class="px-3.5 py-2.5 text-zinc-600 dark:text-zinc-400">
                                            {{ $detail->catatan ?: '-' }}
                                        </td>
                                        <td class="px-3.5 py-2.5">
                                            @if ($detail->maintenance->isNotEmpty())
                                                @foreach ($detail->maintenance as $mnt)
                                                    <span class="font-mono text-[11px] px-2 py-0.5 rounded bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 font-bold">
                                                        {{ $mnt->kode }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-zinc-400 text-[11px]">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @endif
    </flux:modal>
