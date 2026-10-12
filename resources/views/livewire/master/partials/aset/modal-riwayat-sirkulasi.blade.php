    {{-- Modal Buku Riwayat Sirkulasi Peminjaman Aset (Seperti Rekam Medis) --}}
    <flux:modal name="modal-riwayat-sirkulasi" wire:model="showSirkulasiModal" class="w-full max-w-5xl">
        @if ($selectedAset)
            <div class="space-y-5">
                {{-- Header Modal --}}
                <div class="flex items-start justify-between gap-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">Buku Rekam Sirkulasi & Peminjaman: {{ $selectedAset->barang?->nama }}</flux:heading>
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                                {{ $selectedAset->kode_aset }}
                            </span>
                        </div>
                        <flux:subheading class="mt-0.5">
                            Nomor Seri: {{ $selectedAset->nomor_seri ?? '-' }} &bull;
                            Penempatan: {{ $selectedAset->unit?->nama ?? $selectedAset->gudang?->nama ?? 'Sentral' }}
                        </flux:subheading>
                    </div>
                    <div class="flex items-center gap-2" style="margin-right: 2.25rem;">
                        <x-status-badge :status="$selectedAset->status" />
                    </div>
                </div>

                {{-- Ringkasan Statistik Sirkulasi --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/60 text-xs">
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Total Peminjaman</div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $selectedAset->peminjamanDetail->count() }} Kali</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Status Operasional</div>
                        <div class="font-bold text-sm text-purple-600 dark:text-purple-400 mt-0.5">{{ $selectedAset->status }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Kondisi Fisik Terkini</div>
                        <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">{{ str_replace('_', ' ', $selectedAset->kondisi) }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 dark:text-zinc-400">Nilai Perolehan</div>
                        <div class="font-mono font-bold text-sm text-zinc-900 dark:text-zinc-100 mt-0.5">
                            <x-rupiah :value="$selectedAset->nilai_perolehan" />
                        </div>
                    </div>
                </div>

                {{-- Tabel Kronologi Penuh --}}
                <div class="space-y-3">
                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Linimasa Riwayat Peminjaman & Pengembalian Fisik</div>
                    <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700/60 rounded-xl">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold">
                                <tr>
                                    <th class="p-3">Kode Dokumen</th>
                                    <th class="p-3">Unit Peminjam</th>
                                    <th class="p-3">Masa Peminjaman</th>
                                    <th class="p-3">Keperluan</th>
                                    <th class="p-3 text-center">Status</th>
                                    <th class="p-3">Pengembalian Fisik</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700/50">
                                @forelse ($selectedAset->peminjamanDetail->sortByDesc('id') as $d)
                                    @php
                                        $pinjam = $d->permintaanPeminjaman;
                                        $kembali = $d->pengembalianDetail->first();
                                    @endphp
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                        <td class="p-3 font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                            {{ $pinjam?->kode ?? '-' }}
                                        </td>
                                        <td class="p-3">
                                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $pinjam?->unitPeminjam?->nama ?? 'Ruangan' }}</div>
                                            <div class="text-zinc-500 text-[11px]">{{ $pinjam?->pengaju?->name ?? 'Staf' }}</div>
                                        <td class="p-3 text-zinc-700 dark:text-zinc-300">
                                            <div>{{ $pinjam?->tanggal_pinjam?->format('d/m/Y') ?? '-' }} s/d {{ $pinjam?->tanggal_kembali_rencana?->format('d/m/Y') ?? '-' }}</div>
                                        </td>
                                        <td class="p-3 text-zinc-600 dark:text-zinc-400 max-w-xs truncate">
                                            {{ $pinjam?->keperluan ?: '-' }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <x-status-badge :status="$pinjam?->status ?? \App\Enums\StatusPeminjaman::Selesai->value" />
                                        </td>
                                        <td class="p-3">
                                            @if ($kembali)
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ str_replace('_', ' ', $kembali->kondisi_saat_kembali) }}</span>
                                                    <span class="text-[11px] text-zinc-400">&bull; {{ $kembali->pengembalianPeminjaman?->tanggal_kembali?->format('d/m/Y') ?? '-' }}</span>
                                                </div>
                                                @if ($kembali->catatan)
                                                    <div class="text-[11px] text-zinc-500 italic mt-0.5">"{{ $kembali->catatan }}"</div>
                                                @endif
                                            @elseif ($pinjam?->status === \App\Enums\StatusPeminjaman::Dipinjam->value)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                                    Sedang Digunakan
                                                </span>
                                            @else
                                                <span class="text-zinc-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-zinc-500 italic">Belum ada data transaksi sirkulasi.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @endif
    </flux:modal>
