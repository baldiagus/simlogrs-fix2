    {{-- Modal Detail Penerimaan RCV --}}
    <flux:modal wire:model="showDetailModal" class="w-full max-w-[92vw] lg:max-w-6xl">
        @if ($selectedPenerimaan)
            <div class="space-y-4">
                <div style="padding-right: 2.5rem;">
                    <flux:heading size="lg">Detail Penerimaan: {{ $selectedPenerimaan->kode }}</flux:heading>
                    <flux:subheading>Ref PO: {{ $selectedPenerimaan->pesananPembelian?->kode ?? '-' }} - {{ $selectedPenerimaan->pesananPembelian?->pemasok?->nama ?? '-' }}</flux:subheading>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-zinc-500">Gudang</div>
                        <div class="font-medium">{{ $selectedPenerimaan->gudang?->nama ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Surat Jalan</div>
                        <div class="font-medium">{{ $selectedPenerimaan->nomor_surat_jalan }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Tanggal Terima</div>
                        <div class="font-medium">{{ $selectedPenerimaan->tanggal_terima?->format('d/m/Y') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Diterima Oleh</div>
                        <div class="font-medium">{{ $selectedPenerimaan->penerima?->nama ?? '-' }}</div>
                    </div>
                </div>

                @if ($selectedPenerimaan->status === \App\Enums\Pengadaan\StatusPenerimaanPo::Ditolak->value)
                    <div class="p-3.5 bg-red-50 dark:bg-red-950/40 rounded-xl border border-red-200 dark:border-red-800 text-xs text-red-800 dark:text-red-300 space-y-1">
                        <div class="font-bold flex items-center gap-1.5 text-sm text-red-700 dark:text-red-400">
                            <flux:icon.no-symbol class="size-4 shrink-0" />
                            <span>Kiriman Fisik Ini Ditolak di Pintu Masuk Gudang</span>
                        </div>
                        <p>
                            Seluruh komoditas dikembalikan kepada kurir vendor dan tidak dimasukkan ke dalam stok fisik rumah sakit (kuantitas diterima = 0).
                        </p>
                    </div>
                @endif
                @if ($selectedPenerimaan->catatan)
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-sm">
                        <span class="font-semibold text-xs text-zinc-500 block mb-0.5">Catatan</span>
                        {{ $selectedPenerimaan->catatan }}
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left text-zinc-500">
                                <th class="pb-2 font-semibold">Barang</th>
                                <th class="pb-2 font-semibold text-center">Diterima</th>
                                <th class="pb-2 font-semibold text-center">Rusak</th>
                                <th class="pb-2 font-semibold text-center">Nett</th>
                                <th class="pb-2 font-semibold">Batch</th>
                                <th class="pb-2 font-semibold">Kedaluwarsa</th>
                                <th class="pb-2 font-semibold">Rak</th>
                                <th class="pb-2 font-semibold text-right">Harga Satuan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($selectedPenerimaan->detail as $det)
                                <tr>
                                    <td class="py-2 pr-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $det->barang?->nama ?? '-' }}</td>
                                    <td class="py-2 text-center font-mono">{{ number_format($det->jumlah_diterima, 0, ',', '.') }}</td>
                                    <td class="py-2 text-center font-mono {{ $det->jumlah_rusak > 0 ? 'text-red-500 font-semibold' : '' }}">{{ number_format($det->jumlah_rusak, 0, ',', '.') }}</td>
                                    <td class="py-2 text-center font-mono font-semibold text-emerald-600">{{ number_format($det->jumlah_diterima - $det->jumlah_rusak, 0, ',', '.') }}</td>
                                    <td class="py-2 font-mono">{{ $det->nomor_batch }}</td>
                                    <td class="py-2">{{ $det->tanggal_kedaluwarsa?->format('d/m/Y') ?? '-' }}</td>
                                    <td class="py-2">{{ $det->lokasiGudang?->nama ?? $det->lokasiGudang?->kode ?? '-' }}</td>
                                    <td class="py-2 text-right"><x-ui.rupiah :value="$det->harga_satuan" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        @endif
    </flux:modal>
