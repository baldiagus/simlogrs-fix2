    {{-- MODAL DETAIL / BERITA ACARA SERAH TERIMA (BAST) --}}
    @if ($selectedTransfer)
        <flux:modal name="modal-detail-pengalihan" wire:model="showDetailModal" class="w-full max-w-3xl" style="max-height: 90vh; overflow-y: auto;">
            <div class="space-y-5 pb-2" id="printable-bast">
                {{-- Kop Berita Acara --}}
                <div class="border-b-2 border-zinc-800 pb-3 text-center">
                    <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">Rumah Sakit Logistik Sentral</div>
                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">
                        BERITA ACARA SERAH TERIMA PENGALIHAN ASET (BAST)
                    </div>
                    <div class="text-xs font-mono text-zinc-600 dark:text-zinc-400 mt-0.5">
                        Nomor Dokumen: <strong class="text-blue-600">{{ $selectedTransfer->kode }}</strong>
                    </div>
                </div>

                {{-- Status & Informasi Pokok --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <div>
                        <div class="text-zinc-500 text-[11px]">Tanggal Pengajuan</div>
                        <div class="font-semibold">{{ $selectedTransfer->tanggal_pengalihan?->translatedFormat('d F Y') }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-[11px]">Status Dokumen</div>
                        <div>
                            <flux:badge size="sm" :color="$selectedTransfer->status === 'SELESAI' ? 'emerald' : 'blue'">
                                {{ $selectedTransfer->status }}
                            </flux:badge>
                        </div>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-[11px]">Lokasi Asal</div>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $selectedTransfer->nama_lokasi_asal }}</div>
                    </div>
                    <div>
                        <div class="text-zinc-500 text-[11px]">Lokasi Tujuan (Permanen)</div>
                        <div class="font-semibold text-blue-600 dark:text-blue-400">{{ $selectedTransfer->nama_lokasi_tujuan }}</div>
                    </div>
                </div>

                {{-- Narasi Justifikasi --}}
                <div class="text-xs text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <div class="font-bold text-zinc-500 text-[11px] uppercase mb-1">Dasar / Keperluan Pengalihan:</div>
                    <p>{{ $selectedTransfer->alasan }}</p>
                </div>

                {{-- Tabel Rincian Aset --}}
                <div>
                    <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-2">Daftar Aset yang Dimutasi:</div>
                    <table class="w-full text-xs text-left border border-zinc-200 dark:border-zinc-800 rounded-lg overflow-hidden">
                        <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                            <tr>
                                <th class="p-2 w-8">#</th>
                                <th class="p-2">Kode Aset</th>
                                <th class="p-2">Nama Barang & Seri</th>
                                <th class="p-2">Kondisi Awal</th>
                                <th class="p-2">Kondisi Serah Terima</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($selectedTransfer->detail as $i => $item)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40">
                                    <td class="p-2 text-zinc-400">{{ $i + 1 }}</td>
                                    <td class="p-2 font-mono font-semibold">{{ $item->aset?->kode_aset ?? '-' }}</td>
                                    <td class="p-2">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->aset?->barang?->nama ?? '-' }}</div>
                                        <div class="text-[11px] text-zinc-400">SN: {{ $item->aset?->nomor_seri ?? '-' }}</div>
                                    </td>
                                    <td class="p-2">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-zinc-100 dark:bg-zinc-800 font-medium">
                                            {{ $item->kondisi_sebelum }}
                                        </span>
                                    </td>
                                    <td class="p-2 font-semibold">
                                        {{ $item->kondisi_saat_diterima ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Kolom Tanda Tangan Para Pihak --}}
                <div class="grid grid-cols-3 gap-4 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-center text-xs">
                    <div>
                        <div class="text-zinc-400 text-[11px]">Yang Menyerahkan (Asal)</div>
                        <div class="h-12 flex items-center justify-center font-serif italic text-zinc-400 text-xs">
                            [Tercatat di Sistem]
                        </div>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200">{{ $selectedTransfer->pengaju?->nama ?? '-' }}</div>
                        <div class="text-[10px] text-zinc-400">NIP: {{ $selectedTransfer->pengaju?->nip ?? '-' }}</div>
                    </div>

                    <div>
                        <div class="text-zinc-400 text-[11px]">Mengetahui (Pimpinan)</div>
                        <div class="h-12 flex items-center justify-center font-serif italic text-zinc-400 text-xs">
                            @if ($selectedTransfer->penyetuju) [Disetujui Digital] @else - @endif
                        </div>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200">{{ $selectedTransfer->penyetuju?->nama ?? '-' }}</div>
                        <div class="text-[10px] text-zinc-400">NIP: {{ $selectedTransfer->penyetuju?->nip ?? '-' }}</div>
                    </div>

                    <div>
                        <div class="text-zinc-400 text-[11px]">Yang Menerima (Tujuan)</div>
                        <div class="h-12 flex items-center justify-center font-serif italic text-zinc-400 text-xs">
                            @if ($selectedTransfer->penerima) [Diterima Fisik] @else - @endif
                        </div>
                        <div class="font-bold text-zinc-800 dark:text-zinc-200">{{ $selectedTransfer->penerima?->nama ?? '-' }}</div>
                        <div class="text-[10px] text-zinc-400">NIP: {{ $selectedTransfer->penerima?->nip ?? '-' }}</div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-between pt-3 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button type="button" variant="ghost" icon="printer" onclick="window.print()">
                        Cetak BAST
                    </flux:button>
                    <flux:button type="button" variant="primary" wire:click="$set('showDetailModal', false)">
                        Tutup
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
